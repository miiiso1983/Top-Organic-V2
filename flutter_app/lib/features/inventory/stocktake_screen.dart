import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/barcode_scanner.dart';
import '../../shared/widgets/offline_banner.dart';

class _LineItem {
  _LineItem({required this.item, required this.qtyCtrl});
  final Map<String, dynamic> item;
  final TextEditingController qtyCtrl;
}

class StocktakeScreen extends StatefulWidget {
  const StocktakeScreen({super.key});

  @override
  State<StocktakeScreen> createState() => _StocktakeScreenState();
}

class _StocktakeScreenState extends State<StocktakeScreen> {
  final _codeCtrl = TextEditingController();
  final List<_LineItem> _items = [];
  int? _warehouseId;
  Future<List<Map<String, dynamic>>>? _warehousesFuture;
  bool _submitting = false;
  String? _error;
  String? _offlineNotice;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _warehousesFuture ??= _loadWarehouses();
  }

  Future<List<Map<String, dynamic>>> _loadWarehouses() async {
    final repo = context.read<CachedRepository>();
    final res = await repo.getJson('/warehouses', 'warehouses');
    return ((res.data['data'] as List?) ?? [])
        .map((e) => e as Map<String, dynamic>)
        .toList();
  }

  Future<void> _addItem(String code) async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    setState(() => _error = null);
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson(
        '/inventory/barcode',
        'inventory_barcode_$code',
        query: {'code': code},
      );
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      if (body['ok'] == true) {
        final item = body['data'] as Map<String, dynamic>;
        setState(() {
          _items.add(_LineItem(item: item, qtyCtrl: TextEditingController()));
          _offlineNotice = res.fromCache ? res.errorMessage : null;
        });
      } else {
        setState(
          () => _error =
              body['error']?.toString() ??
              AppStrings.t('inventory_stocktake_item_not_found', lang),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _error = AppStrings.t('inventory_stocktake_search_error', lang),
      );
    }
  }

  Future<void> _submit() async {
    if (_warehouseId == null || _items.isEmpty) return;
    final lang = context.read<LocaleProvider>().locale.languageCode;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final payload = {
        'warehouse_id': _warehouseId,
        'items': _items
            .map(
              (l) => {
                'barcode': l.item['barcode'],
                'unit_id': l.item['unit_id'],
                'counted_qty': double.tryParse(l.qtyCtrl.text) ?? 0,
              },
            )
            .toList(),
      };
      final res = await repo.postOrQueue(
        '/inventory/stocktakes',
        payload,
        entity: 'inventory_stocktake',
      );
      if (!mounted) return;
      if (res.data?['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${AppStrings.t('inventory_stocktake_success_prefix', lang)}${res.data?['diff'] ?? ''}',
            ),
          ),
        );
        Navigator.of(context).pop();
      } else {
        setState(
          () => _error =
              res.errorMessage ??
              res.data?['error']?.toString() ??
              AppStrings.t('inventory_stocktake_failed', lang),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _error = AppStrings.t('inventory_stocktake_submit_error', lang),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(title: Text(t('inventory_stocktake_title'))),
      body: Column(
        children: [
          OfflineBanner(message: _offlineNotice),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                FutureBuilder<List<Map<String, dynamic>>>(
                  future: _warehousesFuture,
                  builder: (context, snap) => DropdownButtonFormField<int>(
                    initialValue: _warehouseId,
                    decoration: InputDecoration(
                      labelText: t('inventory_stocktake_warehouse_label'),
                    ),
                    items: (snap.data ?? [])
                        .map(
                          (w) => DropdownMenuItem<int>(
                            value: w['id'] as int,
                            child: Text(w['name'].toString()),
                          ),
                        )
                        .toList(),
                    onChanged: (v) => setState(() => _warehouseId = v),
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _codeCtrl,
                        decoration: InputDecoration(
                          labelText: t('inventory_stocktake_barcode_label'),
                        ),
                      ),
                    ),
                    BarcodeScanButton(
                      onCode: (code) {
                        _codeCtrl.text = code;
                        _addItem(code);
                      },
                    ),
                    IconButton(
                      icon: const Icon(Icons.add),
                      onPressed: () => _addItem(_codeCtrl.text.trim()),
                    ),
                  ],
                ),
                ..._items.asMap().entries.map((e) {
                  final l = e.value;
                  return Card(
                    child: ListTile(
                      title: Text(l.item['name']?.toString() ?? ''),
                      subtitle: Text(l.item['unit_name']?.toString() ?? ''),
                      trailing: SizedBox(
                        width: 80,
                        child: TextField(
                          controller: l.qtyCtrl,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          decoration: InputDecoration(
                            labelText: t('inventory_stocktake_qty_label'),
                          ),
                        ),
                      ),
                      leading: IconButton(
                        icon: const Icon(Icons.delete),
                        onPressed: () => setState(() {
                          l.qtyCtrl.dispose();
                          _items.removeAt(e.key);
                        }),
                      ),
                    ),
                  );
                }),
                const SizedBox(height: 16),
                if (_error != null)
                  Text(_error!, style: const TextStyle(color: Colors.red)),
                ElevatedButton(
                  onPressed: _submitting ? null : _submit,
                  child: _submitting
                      ? const CircularProgressIndicator()
                      : Text(t('inventory_stocktake_save_button')),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _codeCtrl.dispose();
    for (final l in _items) {
      l.qtyCtrl.dispose();
    }
    super.dispose();
  }
}
