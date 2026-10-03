import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';

class CaptainTablesScreen extends StatefulWidget {
  const CaptainTablesScreen({super.key, required this.onSelect});
  final void Function(Map<String, dynamic> table) onSelect;

  @override
  State<CaptainTablesScreen> createState() => _CaptainTablesScreenState();
}

class _CaptainTablesScreenState extends State<CaptainTablesScreen> {
  bool _loading = true;
  String? _error;
  String? _offlineNotice;
  List<dynamic> _tables = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
      _offlineNotice = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson('/pos/tables', 'captain_tables');
      final body = res.data as Map<String, dynamic>;
      setState(() {
        _tables = (body['data'] as List?) ?? [];
        _offlineNotice = res.fromCache ? res.errorMessage : null;
      });
    } catch (e) {
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() => _error = AppStrings.t('captain_tables_load_error', lang));
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Column(
      children: [
        OfflineBanner(message: _offlineNotice),
        Expanded(
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(
                        Icons.error_outline,
                        color: Colors.red,
                        size: 40,
                      ),
                      const SizedBox(height: 8),
                      Text(_error!, textAlign: TextAlign.center),
                      TextButton(onPressed: _load, child: Text(t('retry'))),
                    ],
                  ),
                )
              : RefreshIndicator(onRefresh: _load, child: _grid()),
        ),
      ],
    );
  }

  Widget _grid() {
    return LayoutBuilder(
      builder: (context, constraints) {
        final count = (constraints.maxWidth / 150).floor().clamp(2, 6);
        return GridView.builder(
          padding: const EdgeInsets.all(12),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: count,
            childAspectRatio: 1.05,
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
          ),
          itemCount: _tables.length,
          itemBuilder: (context, i) => _TableCard(
            table: _tables[i] as Map<String, dynamic>,
            onTap: () => widget.onSelect(_tables[i] as Map<String, dynamic>),
          ),
        );
      },
    );
  }
}

class _TableCard extends StatelessWidget {
  const _TableCard({required this.table, required this.onTap});
  final Map<String, dynamic> table;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final isOccupied = table['status']?.toString() == 'occupied';
    final bg = isOccupied ? const Color(0xFFEF4444) : const Color(0xFFFFEB3B);
    final fg = isOccupied ? Colors.white : const Color(0xFF333333);
    return Card(
      color: bg,
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                table['name']?.toString() ?? '',
                style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w800,
                  color: fg,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                '${table['capacity'] ?? table['seats'] ?? 0}${t('captain_tables_chair_suffix')}',
                style: TextStyle(
                  fontSize: 13,
                  color: fg.withValues(alpha: 0.9),
                ),
              ),
              if (table['floor_name'] != null &&
                  table['floor_name'].toString().isNotEmpty)
                Text(
                  table['floor_name'].toString(),
                  style: TextStyle(
                    fontSize: 12,
                    color: fg.withValues(alpha: 0.9),
                  ),
                ),
              if (table['current_order'] != null &&
                  table['current_order'].toString().isNotEmpty)
                Text(
                  '${t('captain_tables_order_prefix')}${table['current_order']}',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: fg,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
