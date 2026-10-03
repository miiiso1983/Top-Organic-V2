import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';

class InventoryReportsScreen extends StatefulWidget {
  const InventoryReportsScreen({super.key});

  @override
  State<InventoryReportsScreen> createState() => _InventoryReportsScreenState();
}

class _InventoryReportsScreenState extends State<InventoryReportsScreen> {
  String _type = 'all';
  bool _loading = false;
  List<Map<String, dynamic>> _rows = [];
  String? _error;
  String? _offlineNotice;

  final _types = ['all', 'raw', 'asset', 'tool', 'clothing', 'prepared'];

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
      _offlineNotice = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson(
        '/reports/inventory',
        'reports_inventory_$_type',
        query: {'type': _type},
      );
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      if (body['ok'] == true) {
        setState(() {
          _rows = ((body['data'] as List?) ?? [])
              .map((e) => e as Map<String, dynamic>)
              .toList();
          _offlineNotice = res.fromCache ? res.errorMessage : null;
        });
      } else {
        setState(
          () => _error =
              body['error']?.toString() ??
              AppStrings.t('inventory_reports_load_failed', _lang),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _error = AppStrings.t('inventory_reports_load_error', _lang),
      );
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _lang = 'ar';

  String _typeLabel(String type, String Function(String) t) {
    switch (type) {
      case 'raw':
        return t('inventory_reports_type_raw');
      case 'asset':
        return t('inventory_reports_type_asset');
      case 'tool':
        return t('inventory_reports_type_tool');
      case 'clothing':
        return t('inventory_reports_type_clothing');
      case 'prepared':
        return t('inventory_reports_type_prepared');
      default:
        return t('inventory_reports_type_all');
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    _lang = lang;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(title: Text(t('inventory_reports_title'))),
      body: Column(
        children: [
          OfflineBanner(message: _offlineNotice),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<String>(
                    initialValue: _type,
                    decoration: InputDecoration(
                      labelText: t('inventory_reports_type_label'),
                    ),
                    items: _types
                        .map(
                          (ty) => DropdownMenuItem(
                            value: ty,
                            child: Text(_typeLabel(ty, t)),
                          ),
                        )
                        .toList(),
                    onChanged: (v) => setState(() => _type = v ?? 'all'),
                  ),
                ),
                const SizedBox(width: 12),
                ElevatedButton(
                  onPressed: _loading ? null : _load,
                  child: _loading
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text(t('inventory_reports_view_button')),
                ),
              ],
            ),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.all(12),
              child: Text(_error!, style: const TextStyle(color: Colors.red)),
            ),
          Expanded(
            child: _rows.isEmpty
                ? Center(child: Text(t('inventory_reports_empty')))
                : ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: _rows.length,
                    separatorBuilder: (_, _) => const Divider(height: 1),
                    itemBuilder: (context, i) {
                      final r = _rows[i];
                      final count = int.tryParse('${r['items_count']}') ?? 0;
                      final qty = double.tryParse('${r['total_qty']}') ?? 0;
                      final value = double.tryParse('${r['total_value']}') ?? 0;
                      return ListTile(
                        title: Text(r['category_type']?.toString() ?? ''),
                        subtitle: Text(
                          '${t('inventory_reports_items_label')}$count${t('inventory_reports_qty_label')}$qty',
                        ),
                        trailing: Text(value.toStringAsFixed(2)),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}
