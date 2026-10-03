import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/kpi_card.dart';
import '../../shared/widgets/offline_banner.dart';
import '../../shared/utils/formatters.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  bool _loading = true;
  String? _error;
  String? _offlineNotice;
  Map<String, dynamic>? _data;

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
      final res = await repo.getJson('/dashboard', 'dashboard');
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      setState(() {
        _data = (body['data'] as Map).cast<String, dynamic>();
        _offlineNotice = res.fromCache ? res.errorMessage : null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _error = AppStrings.t(
          'dashboard_load_error',
          context.read<LocaleProvider>().locale.languageCode,
        ),
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
              child: _data == null
                  ? const SizedBox()
                  : _buildKpiGrid(context, t),
            ),
          ),
        ],
      ),
    );
  }

  /// يبني بطاقات المؤشرات فقط لما يُرجعه الخادم من مفاتيح (الخادم يستبعد ما لا يملك
  /// المستخدم صلاحية الاطلاع عليه)، وإلا يظهر رسالة عند عدم توفر أي مؤشر.
  Widget _buildKpiGrid(BuildContext context, String Function(String) t) {
    final data = _data!;
    final cards = <Widget>[
      if (data.containsKey('today_orders'))
        KpiCard(
          label: t('dashboard_today_orders'),
          value: Formatters.number(data['today_orders']),
          icon: Icons.receipt_long,
          color: Colors.blue,
        ),
      if (data.containsKey('today_sales'))
        KpiCard(
          label: t('dashboard_today_sales'),
          value: Formatters.currency(data['today_sales']),
          icon: Icons.attach_money,
          color: Colors.green,
        ),
      if (data.containsKey('cash_balance'))
        KpiCard(
          label: t('dashboard_cash_balance'),
          value: Formatters.currency(data['cash_balance']),
          icon: Icons.account_balance_wallet,
          color: Colors.teal,
        ),
      if (data.containsKey('low_stock_items'))
        KpiCard(
          label: t('dashboard_low_stock'),
          value: Formatters.number(data['low_stock_items']),
          icon: Icons.warning_amber,
          color: Colors.orange,
        ),
      if (data.containsKey('pending_approvals'))
        KpiCard(
          label: t('dashboard_pending_approvals'),
          value: Formatters.number(data['pending_approvals']),
          icon: Icons.fact_check,
          color: Colors.purple,
        ),
    ];

    if (cards.isEmpty) {
      return Center(child: Text(t('dashboard_no_kpis')));
    }

    return GridView.count(
      padding: const EdgeInsets.all(16),
      crossAxisCount: MediaQuery.sizeOf(context).width > 700 ? 4 : 2,
      childAspectRatio: 1.5,
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      children: cards,
    );
  }
}
