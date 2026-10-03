import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';

class PurchasingScreen extends StatefulWidget {
  const PurchasingScreen({super.key});

  @override
  State<PurchasingScreen> createState() => _PurchasingScreenState();
}

class _PurchasingScreenState extends State<PurchasingScreen> {
  bool _loading = true;
  String? _error;
  String? _offlineNotice;
  List<dynamic> _requests = [];
  List<dynamic> _orders = [];

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
      final reqRes = await repo.getJson(
        '/purchasing/requests',
        'purchase_requests',
      );
      final ordRes = await repo.getJson(
        '/purchasing/orders',
        'purchase_orders',
      );
      if (!mounted) return;
      setState(() {
        _requests = ((reqRes.data as Map)['data'] as List?) ?? [];
        _orders = ((ordRes.data as Map)['data'] as List?) ?? [];
        _offlineNotice = (reqRes.fromCache || ordRes.fromCache)
            ? reqRes.errorMessage
            : null;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() => _error = AppStrings.t('purchasing_load_error', lang));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return DefaultTabController(
      length: 2,
      child: Column(
        children: [
          OfflineBanner(message: _offlineNotice),
          Material(
            color: Colors.white,
            child: TabBar(
              tabs: [
                Tab(text: t('purchasing_tab_requests')),
                Tab(text: t('purchasing_tab_orders')),
              ],
            ),
          ),
          Expanded(
            child: AsyncStateView(
              loading: _loading,
              error: _error,
              onRetry: _load,
              child: TabBarView(
                children: [
                  _list(
                    _requests,
                    t,
                    (r) => '${r['request_no']} — ${r['department'] ?? ''}',
                    (r) =>
                        '${t('purchasing_request_status_label')}${r['status']}${t('purchasing_request_requester_label')}${r['requester'] ?? ''}',
                  ),
                  _list(
                    _orders,
                    t,
                    (o) => '${o['po_no']} — ${o['supplier_name'] ?? ''}',
                    (o) =>
                        '${t('purchasing_order_status_label')}${o['status']}${t('purchasing_order_total_label')}${o['total']}',
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _list(
    List<dynamic> items,
    String Function(String) t,
    String Function(Map) title,
    String Function(Map) subtitle,
  ) {
    if (items.isEmpty) return Center(child: Text(t('purchasing_empty')));
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        itemCount: items.length,
        separatorBuilder: (a, b) => const Divider(height: 1),
        itemBuilder: (context, i) {
          final item = items[i] as Map;
          return ListTile(
            title: Text(title(item)),
            subtitle: Text(subtitle(item)),
          );
        },
      ),
    );
  }
}
