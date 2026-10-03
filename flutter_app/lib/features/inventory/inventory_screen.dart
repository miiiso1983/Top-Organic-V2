import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';

class InventoryScreen extends StatefulWidget {
  const InventoryScreen({super.key});

  @override
  State<InventoryScreen> createState() => _InventoryScreenState();
}

class _InventoryScreenState extends State<InventoryScreen> {
  bool _loading = true;
  String? _error;
  String? _offlineNotice;
  List<dynamic> _items = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson('/inventory', 'inventory');
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      setState(() {
        _items = (body['data'] as List?) ?? [];
        _offlineNotice = res.fromCache ? res.errorMessage : null;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(
        () => _error = AppStrings.t('inventory_screen_load_error', lang),
      );
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return RefreshIndicator(
      onRefresh: _load,
      child: Column(
        children: [
          OfflineBanner(message: _offlineNotice),
          Expanded(
            child: AsyncStateView(
              loading: _loading,
              error: _error,
              onRetry: _load,
              child: _items.isEmpty
                  ? Center(child: Text(t('inventory_screen_empty')))
                  : ListView.separated(
                      itemCount: _items.length,
                      separatorBuilder: (a, b) => const Divider(height: 1),
                      itemBuilder: (context, i) {
                        final it = _items[i] as Map;
                        final qty = double.tryParse('${it['total_qty']}') ?? 0;
                        final minQty = double.tryParse('${it['min_qty']}') ?? 0;
                        final low = minQty > 0 && qty <= minQty;
                        return ListTile(
                          leading: Icon(
                            Icons.inventory_2,
                            color: low
                                ? Colors.red
                                : Theme.of(context).colorScheme.primary,
                          ),
                          title: Text(it['name'].toString()),
                          subtitle: Text(
                            '${it['category_name'] ?? ''} • ${it['unit_name'] ?? ''}',
                          ),
                          trailing: Text(
                            '$qty',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              color: low ? Colors.red : null,
                            ),
                          ),
                        );
                      },
                    ),
            ),
          ),
        ],
      ),
    );
  }
}
