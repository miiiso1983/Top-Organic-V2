import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';
import '../../shared/utils/formatters.dart';
import 'order_details_screen.dart';

class OrdersTab extends StatefulWidget {
  const OrdersTab({super.key});

  @override
  State<OrdersTab> createState() => _OrdersTabState();
}

class _OrdersTabState extends State<OrdersTab> {
  bool _loading = true;
  String? _error;
  String? _offlineNotice;
  List<dynamic> _orders = [];
  bool _pendingOnly = true;

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
      final res = await repo.getJson('/orders', 'orders');
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      setState(() {
        _orders = (body['data'] as List?) ?? [];
        _offlineNotice = res.fromCache ? res.errorMessage : null;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() => _error = AppStrings.t('sales_orders_load_error', lang));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _updateStatus(int id, String status) async {
    final api = context.read<ApiClient>();
    try {
      await api.post('/orders/$id/status', data: {'status': status});
      _load();
    } catch (_) {
      if (mounted) {
        final lang = context.read<LocaleProvider>().locale.languageCode;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(AppStrings.t('sales_orders_update_error', lang)),
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final displayed = _pendingOnly
        ? _orders
              .where((o) => (o as Map)['status']?.toString() == 'pending')
              .toList()
        : _orders;
    return RefreshIndicator(
      onRefresh: _load,
      child: Column(
        children: [
          OfflineBanner(message: _offlineNotice),
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
            child: Row(
              children: [
                ChoiceChip(
                  label: Text(t('sales_orders_pending_only')),
                  selected: _pendingOnly,
                  onSelected: (_) => setState(() => _pendingOnly = true),
                ),
                const SizedBox(width: 8),
                ChoiceChip(
                  label: Text(t('sales_orders_all')),
                  selected: !_pendingOnly,
                  onSelected: (_) => setState(() => _pendingOnly = false),
                ),
              ],
            ),
          ),
          Expanded(
            child: AsyncStateView(
              loading: _loading,
              error: _error,
              onRetry: _load,
              child: displayed.isEmpty
                  ? Center(child: Text(t('sales_orders_empty')))
                  : ListView.builder(
                      padding: const EdgeInsets.all(12),
                      itemCount: displayed.length,
                      itemBuilder: (context, i) {
                        final o = displayed[i] as Map;
                        final status = o['status']?.toString() ?? 'pending';
                        return Card(
                          child: ListTile(
                            title: Text(
                              '${o['order_no']} — ${Formatters.currency(o['total'])}',
                            ),
                            subtitle: Text(
                              '${o['order_type']} • ${o['table_name'] ?? ''}',
                            ),
                            trailing: Chip(
                              label: Text(
                                status,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 11,
                                ),
                              ),
                              backgroundColor:
                                  _statusColors[status] ?? Colors.grey,
                            ),
                            onTap: () => _onOrderTap(o),
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

  Future<void> _onOrderTap(Map order) async {
    final orderMap = order as Map<String, dynamic>;
    if (orderMap['status']?.toString() == 'pending') {
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => OrderDetailsScreen(
            orderId: orderMap['id'] as int,
            initialOrder: orderMap,
          ),
        ),
      );
      _load();
    } else {
      _showActions(orderMap);
    }
  }

  void _showActions(Map order) {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    showModalBottomSheet(
      context: context,
      builder: (_) => SafeArea(
        child: Wrap(
          children:
              [
                    'confirmed',
                    'preparing',
                    'ready',
                    'served',
                    'completed',
                    'cancelled',
                  ]
                  .map(
                    (s) => ListTile(
                      title: Text(
                        '${AppStrings.t('sales_orders_update_to_prefix', lang)}$s',
                      ),
                      onTap: () {
                        Navigator.pop(context);
                        _updateStatus(order['id'] as int, s);
                      },
                    ),
                  )
                  .toList(),
        ),
      ),
    );
  }
}
