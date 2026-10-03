import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';

class KitchenScreen extends StatefulWidget {
  const KitchenScreen({super.key});

  @override
  State<KitchenScreen> createState() => _KitchenScreenState();
}

class _KitchenScreenState extends State<KitchenScreen> {
  bool _loading = true;
  String? _error;
  String? _offlineNotice;
  List<dynamic> _orders = [];
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _load();
    _timer = Timer.periodic(
      const Duration(seconds: 20),
      (_) => _load(silent: true),
    );
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent)
      setState(() {
        _loading = true;
        _error = null;
      });
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson('/kitchen/orders', 'kitchen_orders');
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      setState(() {
        _orders = (body['orders'] as List?) ?? [];
        _offlineNotice = res.fromCache ? res.errorMessage : null;
      });
    } catch (e) {
      if (!silent && mounted) {
        final lang = context.read<LocaleProvider>().locale.languageCode;
        setState(() => _error = AppStrings.t('kitchen_load_error', lang));
      }
    } finally {
      if (!silent && mounted) setState(() => _loading = false);
    }
  }

  Future<void> _updateItemStatus(int itemId, String status) async {
    final api = context.read<ApiClient>();
    try {
      await api.post('/kitchen/item/$itemId/status', data: {'status': status});
      _load(silent: true);
    } catch (_) {
      if (mounted) {
        final lang = context.read<LocaleProvider>().locale.languageCode;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(AppStrings.t('kitchen_update_error', lang))),
        );
      }
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
              child: _orders.isEmpty
                  ? Center(child: Text(t('kitchen_no_active_orders')))
                  : GridView.builder(
                      padding: const EdgeInsets.all(10),
                      gridDelegate:
                          const SliverGridDelegateWithMaxCrossAxisExtent(
                            maxCrossAxisExtent: 320,
                            mainAxisExtent: 260,
                            mainAxisSpacing: 10,
                            crossAxisSpacing: 10,
                          ),
                      itemCount: _orders.length,
                      itemBuilder: (context, i) {
                        final o = _orders[i] as Map;
                        final overdue = o['overdue'] == true;
                        final items = (o['items'] as List?) ?? [];
                        return Card(
                          color: overdue ? const Color(0xFFFEE2E2) : null,
                          child: Padding(
                            padding: const EdgeInsets.all(10),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${o['order_no']} — ${o['table_name'] ?? o['order_type']}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text(
                                  '${o['elapsed']}${t('kitchen_minutes_suffix')}',
                                  style: TextStyle(
                                    color: overdue ? Colors.red : Colors.grey,
                                  ),
                                ),
                                const Divider(),
                                Expanded(
                                  child: ListView(
                                    children: items.map((it) {
                                      final m = it as Map;
                                      return ListTile(
                                        dense: true,
                                        contentPadding: EdgeInsets.zero,
                                        title: Text(
                                          '${m['name']} × ${m['qty']}',
                                        ),
                                        subtitle: Text(m['status'].toString()),
                                        trailing: PopupMenuButton<String>(
                                          onSelected: (s) => _updateItemStatus(
                                            m['item_id'] as int,
                                            s,
                                          ),
                                          itemBuilder: (_) => [
                                            PopupMenuItem(
                                              value: 'preparing',
                                              child: Text(
                                                t('kitchen_status_preparing'),
                                              ),
                                            ),
                                            PopupMenuItem(
                                              value: 'ready',
                                              child: Text(
                                                t('kitchen_status_ready'),
                                              ),
                                            ),
                                            PopupMenuItem(
                                              value: 'served',
                                              child: Text(
                                                t('kitchen_status_served'),
                                              ),
                                            ),
                                          ],
                                        ),
                                      );
                                    }).toList(),
                                  ),
                                ),
                              ],
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
