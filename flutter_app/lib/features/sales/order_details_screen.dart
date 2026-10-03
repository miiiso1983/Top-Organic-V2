import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/network/api_client.dart';
import '../../core/sync/sync_manager.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/utils/formatters.dart';
import '../../shared/widgets/offline_banner.dart';

/// شاشة تفاصيل الطلب المعلق مع إجراءات المدير: موافقة، رفض، تعديل.
class OrderDetailsScreen extends StatefulWidget {
  const OrderDetailsScreen({
    super.key,
    required this.orderId,
    this.initialOrder,
  });
  final int orderId;
  final Map<String, dynamic>? initialOrder;

  @override
  State<OrderDetailsScreen> createState() => _OrderDetailsScreenState();
}

class _OrderDetailsScreenState extends State<OrderDetailsScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _order;
  List<dynamic> _items = [];
  bool _isEditing = false;
  bool _submitting = false;
  final _notesController = TextEditingController();
  final Map<int, double> _editedQty = {};

  static const _statusColors = {
    'pending': Colors.grey,
    'confirmed': Colors.blue,
    'preparing': Colors.orange,
    'ready': Colors.teal,
    'served': Colors.green,
    'completed': Colors.green,
    'cancelled': Colors.red,
    'delivering': Colors.purple,
  };

  String _statusLabel(String status, String Function(String) t) {
    return {
          'pending': t('order_status_pending'),
          'confirmed': t('order_status_confirmed'),
          'preparing': t('order_status_preparing'),
          'ready': t('order_status_ready'),
          'served': t('order_status_served'),
          'delivering': t('order_status_delivering'),
          'completed': t('order_status_completed'),
          'cancelled': t('order_status_cancelled'),
        }[status] ??
        status;
  }

  String _typeLabel(String? type, String Function(String) t) {
    return {
          'dine_in': t('order_type_dine_in'),
          'takeaway': t('order_type_takeaway'),
          'delivery': t('order_type_delivery'),
          'online': t('order_type_online'),
        }[type] ??
        (type ?? '-');
  }

  @override
  void initState() {
    super.initState();
    if (widget.initialOrder != null) {
      _order = widget.initialOrder;
      _notesController.text = _order?['notes']?.toString() ?? '';
    }
    _loadDetails();
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _loadDetails() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<ApiClient>();
      final res = await api.get('/orders/${widget.orderId}');
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      if (body['ok'] == true) {
        final data = body['data'] as Map<String, dynamic>;
        setState(() {
          _order = data['order'] as Map<String, dynamic>;
          _items = (data['items'] as List?) ?? [];
          _notesController.text = _order?['notes']?.toString() ?? '';
          _loading = false;
        });
      } else {
        final lang = context.read<LocaleProvider>().locale.languageCode;
        setState(() {
          _error =
              body['error']?.toString() ??
              AppStrings.t('order_details_load_error', lang);
          _loading = false;
        });
      }
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() {
        _error = AppStrings.t('order_details_connection_error', lang);
        _loading = false;
      });
    }
  }

  Future<void> _submit(String action) async {
    if (_submitting) return;
    setState(() => _submitting = true);
    final lang = context.read<LocaleProvider>().locale.languageCode;
    try {
      final api = context.read<ApiClient>();
      final payload = <String, dynamic>{
        'action': action,
        'notes': _notesController.text.trim(),
      };
      if (action == 'edit') {
        payload['items'] = _items
            .where(
              (it) =>
                  (_editedQty[(it as Map)['id'] as int] ??
                      Formatters.toDouble(it['qty'])) >
                  0,
            )
            .map(
              (it) => {
                'id': (it as Map)['id'],
                'qty': _editedQty[it['id']] ?? Formatters.toDouble(it['qty']),
              },
            )
            .toList();
      }
      final res = await api.post(
        '/orders/${widget.orderId}/manage',
        data: payload,
      );
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      if (body['ok'] == true) {
        final data = body['data'] as Map<String, dynamic>;
        setState(() {
          _order = data['order'] as Map<String, dynamic>;
          _items = (data['items'] as List?) ?? [];
          _isEditing = false;
          _editedQty.clear();
          _notesController.text = _order?['notes']?.toString() ?? '';
        });
        // محاولة مزامنة فورية للإشعار بالتغييرات
        context.read<SyncManager>().syncNow().catchError((_) {});
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_actionSuccessMessage(action, lang))),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              body['error']?.toString() ??
                  AppStrings.t('order_details_action_failed', lang),
            ),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AppStrings.t('order_details_no_internet', lang)),
        ),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  String _actionSuccessMessage(String action, String lang) {
    switch (action) {
      case 'approve':
        return AppStrings.t('order_details_approved', lang);
      case 'reject':
        return AppStrings.t('order_details_rejected', lang);
      case 'edit':
        return AppStrings.t('order_details_edited', lang);
      default:
        return AppStrings.t('order_details_updated', lang);
    }
  }

  void _startEdit() {
    _editedQty.clear();
    for (final it in _items) {
      final id = (it as Map)['id'] as int;
      _editedQty[id] = Formatters.toDouble(it['qty']);
    }
    setState(() => _isEditing = true);
  }

  void _cancelEdit() {
    setState(() {
      _isEditing = false;
      _editedQty.clear();
    });
  }

  void _updateQty(int itemId, double delta) {
    final current = _editedQty[itemId] ?? 0;
    final next = (current + delta).clamp(0, 999).toDouble();
    setState(() => _editedQty[itemId] = next);
  }

  void _setQty(int itemId, double value) {
    setState(() => _editedQty[itemId] = value.clamp(0, 999).toDouble());
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final status = _order?['status']?.toString() ?? 'pending';
    return Scaffold(
      appBar: AppBar(
        title: Text(
          _order == null
              ? t('order_details_title')
              : '${t('order_details_order_prefix')}${_order!['order_no']}',
        ),
        actions: [
          if (_isEditing)
            TextButton(
              onPressed: _submitting ? null : _cancelEdit,
              child: Text(
                t('cancel'),
                style: const TextStyle(color: Colors.black87),
              ),
            ),
        ],
      ),
      body: AsyncStateView(
        loading: _loading,
        error: _error,
        onRetry: _loadDetails,
        child: _order == null
            ? Center(child: Text(t('order_details_no_data')))
            : Column(
                children: [
                  _buildHeader(status, t),
                  Expanded(child: _buildItemsList(t)),
                  _buildNotesField(t),
                  _buildActions(status, t),
                ],
              ),
      ),
    );
  }

  Widget _buildHeader(String status, String Function(String) t) {
    return Card(
      margin: const EdgeInsets.all(12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Chip(
                  label: Text(
                    _statusLabel(status, t),
                    style: const TextStyle(color: Colors.white, fontSize: 12),
                  ),
                  backgroundColor: _statusColors[status] ?? Colors.grey,
                ),
                const Spacer(),
                Text(
                  Formatters.currency(_order?['total']),
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                const Icon(
                  Icons.table_restaurant,
                  size: 18,
                  color: Colors.grey,
                ),
                const SizedBox(width: 6),
                Text(
                  _order?['table_name']?.toString().isNotEmpty == true
                      ? '${t('order_details_table_prefix')}${_order!['table_name']}'
                      : t('order_details_no_table'),
                ),
                const SizedBox(width: 16),
                const Icon(Icons.fastfood, size: 18, color: Colors.grey),
                const SizedBox(width: 6),
                Text(_typeLabel(_order?['order_type']?.toString(), t)),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                const Icon(Icons.access_time, size: 18, color: Colors.grey),
                const SizedBox(width: 6),
                Text(_order?['created_at']?.toString() ?? '-'),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildItemsList(String Function(String) t) {
    return ListView.builder(
      padding: const EdgeInsets.symmetric(horizontal: 12),
      itemCount: _items.length,
      itemBuilder: (context, i) {
        final it = _items[i] as Map;
        final itemId = it['id'] as int;
        final qty = _isEditing
            ? (_editedQty[itemId] ?? Formatters.toDouble(it['qty']))
            : Formatters.toDouble(it['qty']);
        return Card(
          child: ListTile(
            title: Text(
              it['name']?.toString() ?? t('order_details_item_fallback'),
            ),
            subtitle: Text(
              '${Formatters.currency(it['unit_price'])} × ${Formatters.number(qty)}',
            ),
            trailing: _isEditing
                ? Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      IconButton(
                        icon: const Icon(
                          Icons.remove_circle_outline,
                          color: Colors.red,
                        ),
                        onPressed: () => _updateQty(itemId, -1),
                      ),
                      SizedBox(
                        width: 48,
                        child: TextFormField(
                          key: ValueKey(qty),
                          textAlign: TextAlign.center,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          initialValue: Formatters.number(qty),
                          onChanged: (v) =>
                              _setQty(itemId, Formatters.toDouble(v)),
                          decoration: const InputDecoration(
                            contentPadding: EdgeInsets.zero,
                            isDense: true,
                          ),
                        ),
                      ),
                      IconButton(
                        icon: const Icon(
                          Icons.add_circle_outline,
                          color: Colors.green,
                        ),
                        onPressed: () => _updateQty(itemId, 1),
                      ),
                    ],
                  )
                : Text(
                    Formatters.currency(it['total']),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
          ),
        );
      },
    );
  }

  Widget _buildNotesField(String Function(String) t) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      child: TextField(
        controller: _notesController,
        enabled: !_submitting,
        maxLines: 2,
        textDirection: TextDirection.rtl,
        decoration: InputDecoration(
          labelText: t('order_details_notes_label'),
          hintText: t('order_details_notes_hint'),
          alignLabelWithHint: true,
        ),
      ),
    );
  }

  Widget _buildActions(String status, String Function(String) t) {
    if (status != 'pending') {
      return Padding(
        padding: const EdgeInsets.all(16),
        child: Center(child: Text(t('order_details_no_actions'))),
      );
    }
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (_isEditing)
              ElevatedButton.icon(
                onPressed: _submitting ? null : () => _submit('edit'),
                icon: _submitting
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.save),
                label: Text(t('order_details_save_edits')),
              )
            else
              Row(
                children: [
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: _submitting ? null : () => _submit('approve'),
                      icon: const Icon(Icons.check_circle),
                      label: Text(t('order_details_approve')),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: _submitting ? null : () => _submit('reject'),
                      icon: const Icon(Icons.cancel),
                      label: Text(t('order_details_reject')),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.red,
                      ),
                    ),
                  ),
                ],
              ),
            const SizedBox(height: 8),
            if (!_isEditing)
              OutlinedButton.icon(
                onPressed: _submitting ? null : _startEdit,
                icon: const Icon(Icons.edit),
                label: Text(t('order_details_edit_items')),
              ),
          ],
        ),
      ),
    );
  }
}
