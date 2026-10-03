import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/kpi_card.dart';
import '../../shared/widgets/offline_banner.dart';

class ReportsScreen extends StatefulWidget {
  const ReportsScreen({super.key});

  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  bool _loading = true;
  String? _error;
  String? _offlineNotice;
  Map<String, dynamic>? _sales;
  Map<String, dynamic>? _profit;

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
      final salesRes = await repo.getJson('/reports/sales', 'reports_sales');
      final profitRes = await repo.getJson('/reports/profit', 'reports_profit');
      if (!mounted) return;
      setState(() {
        _sales = ((salesRes.data as Map)['data'] as Map?)
            ?.cast<String, dynamic>();
        _profit = ((profitRes.data as Map)['data'] as Map?)
            ?.cast<String, dynamic>();
        _offlineNotice = (salesRes.fromCache || profitRes.fromCache)
            ? salesRes.errorMessage
            : null;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() => _error = AppStrings.t('reports_load_error', lang));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final summary = (_sales?['summary'] as Map?) ?? {};
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
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text(
                    t('reports_sales_title'),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 10),
                  GridView.count(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    crossAxisCount: MediaQuery.sizeOf(context).width > 700
                        ? 3
                        : 2,
                    childAspectRatio: 1.6,
                    mainAxisSpacing: 10,
                    crossAxisSpacing: 10,
                    children: [
                      KpiCard(
                        label: t('reports_orders_count'),
                        value: '${summary['orders_count'] ?? 0}',
                        icon: Icons.receipt,
                      ),
                      KpiCard(
                        label: t('reports_total_sales'),
                        value: '${summary['total_sales'] ?? 0}',
                        icon: Icons.attach_money,
                        color: Colors.green,
                      ),
                      KpiCard(
                        label: t('reports_avg_order'),
                        value: '${summary['avg_order'] ?? 0}',
                        icon: Icons.trending_up,
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),
                  Text(
                    t('reports_profit_loss_title'),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 10),
                  GridView.count(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    crossAxisCount: MediaQuery.sizeOf(context).width > 700
                        ? 3
                        : 2,
                    childAspectRatio: 1.6,
                    mainAxisSpacing: 10,
                    crossAxisSpacing: 10,
                    children: [
                      KpiCard(
                        label: t('reports_sales_label'),
                        value: '${_profit?['sales'] ?? 0}',
                        color: Colors.green,
                      ),
                      KpiCard(
                        label: t('reports_cogs_label'),
                        value: '${_profit?['cogs'] ?? 0}',
                        color: Colors.orange,
                      ),
                      KpiCard(
                        label: t('reports_expenses_label'),
                        value: '${_profit?['expenses'] ?? 0}',
                        color: Colors.red,
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
