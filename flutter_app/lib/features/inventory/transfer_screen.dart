import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/barcode_scanner.dart';
import '../../shared/widgets/offline_banner.dart';

class _LineItem {
  _LineItem({
    required this.item,
    required this.qtyCtrl,
    required this.notesCtrl,
  });
  final Map<String, dynamic> item;
  final TextEditingController qtyCtrl;
  final TextEditingController notesCtrl;
}

class TransferScreen extends StatefulWidget {
  const TransferScreen({super.key});

  @override
  State<TransferScreen> createState() => _TransferScreenState();
}

class _TransferScreenState extends State<TransferScreen> {
  final _codeCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();
  final List<_LineItem> _items = [];
  int? _fromWarehouse;
  int? _toWarehouse;
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
          _items.add(
            _LineItem(
              item: item,
              qtyCtrl: TextEditingController(text: '1'),
              notesCtrl: TextEditingController(),
            ),
          );
          _offlineNotice = res.fromCache ? res.errorMessage : null;
        });
      } else {
        setState(
          () => _error =
              body['error']?.toString() ??
              AppStrings.t('inventory_transfer_item_not_found', lang),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _error = AppStrings.t('inventory_transfer_search_error', lang),
      );
    }
  }

  Future<void> _submit() async {
    if (_fromWarehouse == null || _toWarehouse == null || _items.isEmpty)
      return;
    final lang = context.read<LocaleProvider>().locale.languageCode;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final payload = {
        'from_warehouse_id': _fromWarehouse,
        'to_warehouse_id': _toWarehouse,
        'notes': _notesCtrl.text,
        'items': _items
            .map(
              (l) => {
                'barcode': l.item['barcode'],
                'unit_id': l.item['unit_id'],
                'qty': double.tryParse(l.qtyCtrl.text) ?? 0,
                'notes': l.notesCtrl.text,
              },
            )
            .toList(),
      };
      final res = await repo.postOrQueue(
        '/inventory/transfers',
        payload,
        entity: 'inventory_transfer',
      );
      if (!mounted) return;
      if (res.data?['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${AppStrings.t('inventory_transfer_success_prefix', lang)}${res.data?['transfer_no'] ?? ''}',
            ),
          ),
        );
        Navigator.of(context).pop();
      } else {
        setState(
          () => _error =
              res.errorMessage ??
              res.data?['error']?.toString() ??
              AppStrings.t('inventory_transfer_failed', lang),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _error = AppStrings.t('inventory_transfer_submit_error', lang),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Widget _warehouseField(
    String label,
    int? value,
    void Function(int?) onChanged,
  ) {
    return FutureBuilder<List<Map<String, dynamic>>>(
      future: _warehousesFuture,
      builder: (context, snap) => DropdownButtonFormField<int>(
        initialValue: value,
        decoration: InputDecoration(labelText: label),
        items: (snap.data ?? [])
            .map(
              (w) => DropdownMenuItem<int>(
                value: w['id'] as int,
                child: Text(w['name'].toString()),
              ),
            )
            .toList(),
        onChanged: onChanged,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(title: Text(t('inventory_transfer_title'))),
      body: Column(
        children: [
          OfflineBanner(message: _offlineNotice),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _warehouseField(
                  t('inventory_transfer_from_label'),
                  _fromWarehouse,
                  (v) => setState(() => _fromWarehouse = v),
                ),
                const SizedBox(height: 12),
                _warehouseField(
                  t('inventory_transfer_to_label'),
                  _toWarehouse,
                  (v) => setState(() => _toWarehouse = v),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _codeCtrl,
                        decoration: InputDecoration(
                          labelText: t('inventory_transfer_barcode_label'),
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
                if (_items.isNotEmpty)
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        children: _items.asMap().entries.map((e) {
                          final l = e.value;
                          return Padding(
                            padding: const EdgeInsets.symmetric(vertical: 4),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Text(l.item['name']?.toString() ?? ''),
                                ),
                                SizedBox(
                                  width: 80,
                                  child: TextField(
                                    controller: l.qtyCtrl,
                                    keyboardType:
                                        const TextInputType.numberWithOptions(
                                          decimal: true,
                                        ),
                                    decoration: InputDecoration(
                                      labelText: t(
                                        'inventory_transfer_qty_label',
                                      ),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Text(l.item['unit_name']?.toString() ?? ''),
                                IconButton(
                                  icon: const Icon(Icons.delete),
                                  onPressed: () => setState(() {
                                    l.qtyCtrl.dispose();
                                    l.notesCtrl.dispose();
                                    _items.removeAt(e.key);
                                  }),
                                ),
                              ],
                            ),
                          );
                        }).toList(),
                      ),
                    ),
                  ),
                const SizedBox(height: 12),
                TextField(
                  controller: _notesCtrl,
                  decoration: InputDecoration(
                    labelText: t('inventory_transfer_notes_label'),
                  ),
                ),
                const SizedBox(height: 16),
                if (_error != null)
                  Text(_error!, style: const TextStyle(color: Colors.red)),
                ElevatedButton(
                  onPressed: _submitting ? null : _submit,
                  child: _submitting
                      ? const CircularProgressIndicator()
                      : Text(t('inventory_transfer_submit_button')),
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
    _notesCtrl.dispose();
    for (final l in _items) {
      l.qtyCtrl.dispose();
      l.notesCtrl.dispose();
    }
    super.dispose();
  }
}
