import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/barcode_scanner.dart';
import '../../shared/widgets/offline_banner.dart';

class StockMovementScreen extends StatefulWidget {
  const StockMovementScreen({
    super.key,
    required this.movementType,
    required this.title,
    this.item,
  });
  final String movementType; // 'in' or 'out'
  final String title;
  final Map<String, dynamic>? item;

  @override
  State<StockMovementScreen> createState() => _StockMovementScreenState();
}

class _StockMovementScreenState extends State<StockMovementScreen> {
  final _codeCtrl = TextEditingController();
  final _qtyCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();
  Map<String, dynamic>? _item;
  int? _warehouseId;
  Future<List<Map<String, dynamic>>>? _warehousesFuture;
  bool _submitting = false;
  String? _error;
  String? _offlineNotice;

  @override
  void initState() {
    super.initState();
    _item = widget.item;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _warehousesFuture ??= _loadWarehouses();
  }

  Future<List<Map<String, dynamic>>> _loadWarehouses() async {
    final repo = context.read<CachedRepository>();
    final res = await repo.getJson('/warehouses', 'warehouses');
    final body = res.data as Map<String, dynamic>;
    return ((body['data'] as List?) ?? [])
        .map((e) => e as Map<String, dynamic>)
        .toList();
  }

  Future<void> _lookup(String code) async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    setState(() {
      _error = null;
      _item = null;
      _offlineNotice = null;
    });
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
        setState(() {
          _item = body['data'] as Map<String, dynamic>?;
          _offlineNotice = res.fromCache ? res.errorMessage : null;
        });
      } else {
        setState(
          () => _error =
              body['error']?.toString() ??
              AppStrings.t('inventory_stock_movement_item_not_found', lang),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _error = AppStrings.t(
          'inventory_stock_movement_search_error',
          lang,
        ),
      );
    }
  }

  Future<void> _submit() async {
    if (_item == null || _warehouseId == null || _qtyCtrl.text.trim().isEmpty)
      return;
    final lang = context.read<LocaleProvider>().locale.languageCode;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.postOrQueue('/inventory/movements', {
        'barcode': _item!['barcode'],
        'warehouse_id': _warehouseId,
        'movement_type': widget.movementType,
        'qty': double.tryParse(_qtyCtrl.text) ?? 0,
        'unit_id': _item!['unit_id'],
        'notes': _notesCtrl.text,
      }, entity: 'inventory_movement');
      if (!mounted) return;
      if (res.data?['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${AppStrings.t('inventory_stock_movement_success_prefix', lang)}${res.data?['balance'] ?? ''}',
            ),
          ),
        );
        Navigator.of(context).pop();
      } else {
        setState(
          () => _error =
              res.errorMessage ??
              res.data?['error']?.toString() ??
              AppStrings.t('inventory_stock_movement_failed', lang),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _error = AppStrings.t(
          'inventory_stock_movement_submit_error',
          lang,
        ),
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
      appBar: AppBar(title: Text(widget.title)),
      body: Column(
        children: [
          OfflineBanner(message: _offlineNotice),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (_item == null) _buildSearch(t) else _buildItemCard(),
                const SizedBox(height: 12),
                FutureBuilder<List<Map<String, dynamic>>>(
                  future: _warehousesFuture,
                  builder: (context, snap) => DropdownButtonFormField<int>(
                    initialValue: _warehouseId,
                    decoration: InputDecoration(
                      labelText: t('inventory_stock_movement_warehouse_label'),
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
                TextField(
                  controller: _qtyCtrl,
                  decoration: InputDecoration(
                    labelText:
                        '${t('inventory_stock_movement_qty_label')} ${_item != null ? '(${_item!['unit_name'] ?? ''})' : ''}',
                  ),
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _notesCtrl,
                  decoration: InputDecoration(
                    labelText: t('inventory_stock_movement_notes_label'),
                  ),
                ),
                const SizedBox(height: 16),
                if (_error != null)
                  Text(_error!, style: const TextStyle(color: Colors.red)),
                ElevatedButton(
                  onPressed: _submitting ? null : _submit,
                  child: _submitting
                      ? const CircularProgressIndicator()
                      : Text(
                          widget.movementType == 'in'
                              ? t('inventory_stock_movement_submit_in')
                              : t('inventory_stock_movement_submit_out'),
                        ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSearch(String Function(String) t) {
    return Row(
      children: [
        Expanded(
          child: TextField(
            controller: _codeCtrl,
            decoration: InputDecoration(
              labelText: t('inventory_stock_movement_barcode_label'),
            ),
          ),
        ),
        BarcodeScanButton(
          onCode: (code) {
            _codeCtrl.text = code;
            _lookup(code);
          },
        ),
        IconButton(
          icon: const Icon(Icons.search),
          onPressed: () => _lookup(_codeCtrl.text.trim()),
        ),
      ],
    );
  }

  Widget _buildItemCard() {
    return Card(
      child: ListTile(
        title: Text(_item!['name']?.toString() ?? ''),
        subtitle: Text(
          '${(_item!['category_type'] ?? '')} • ${(_item!['unit_name'] ?? '')}',
        ),
        trailing: IconButton(
          icon: const Icon(Icons.clear),
          onPressed: () => setState(() => _item = null),
        ),
      ),
    );
  }

  @override
  void dispose() {
    _codeCtrl.dispose();
    _qtyCtrl.dispose();
    _notesCtrl.dispose();
    super.dispose();
  }
}
