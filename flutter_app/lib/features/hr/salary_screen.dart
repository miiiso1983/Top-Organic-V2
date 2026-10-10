import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';
import 'salary_detail_screen.dart';

/// شاشة كشف الراتب: الشهر الحالي والسجل التاريخي (تظهر فور التوليد بغض النظر عن حالة الاعتماد)
class SalaryScreen extends StatefulWidget {
  const SalaryScreen({super.key});

  @override
  State<SalaryScreen> createState() => _SalaryScreenState();
}

class _SalaryScreenState extends State<SalaryScreen> {
  bool _loading = true;
  String? _error;
  List<dynamic> _payrolls = [];

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
      final api = context.read<ApiClient>();
      final res = await api.get('/payroll/history');
      final body = res.data as Map<String, dynamic>;
      setState(() {
        _payrolls = (body['data'] as List?) ?? [];
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() {
        _error =
            '${AppStrings.t('hr_salary_load_error_prefix', lang)}${e.toString()}';
        _loading = false;
      });
    }
  }

  String _statusLabel(String status, String Function(String) t) {
    return {
          'approved': t('hr_salary_status_approved'),
          'paid': t('hr_salary_status_paid'),
          'draft': t('hr_salary_status_draft'),
        }[status] ??
        status;
  }

  /// تحويل آمن للقيم القادمة من الخادم (قد تصل كنص أو رقم) إلى عدد صحيح
  int _toInt(dynamic v) {
    if (v is int) return v;
    if (v is num) return v.toInt();
    return int.tryParse(v?.toString() ?? '') ?? 0;
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(title: Text(t('hr_salary_title'))),
      body: AsyncStateView(
        loading: _loading,
        error: _error,
        onRetry: _load,
        child: RefreshIndicator(
          onRefresh: _load,
          child: _payrolls.isEmpty
              ? ListView(
                  children: [
                    Padding(
                      padding: const EdgeInsets.all(32),
                      child: Center(child: Text(t('hr_salary_empty'))),
                    ),
                  ],
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: _payrolls.length,
                  itemBuilder: (context, i) {
                    final p = _payrolls[i] as Map<String, dynamic>;
                    return Card(
                      child: ListTile(
                        title: Text(p['period']?.toString() ?? ''),
                        subtitle: Text(
                          '${t('hr_salary_net_label')}${p['net_salary'] ?? 0}',
                        ),
                        trailing: Chip(
                          label: Text(
                            _statusLabel(p['status']?.toString() ?? '', t),
                          ),
                        ),
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => SalaryDetailScreen(
                              payrollId: _toInt(p['id']),
                              period: p['period']?.toString() ?? '',
                            ),
                          ),
                        ),
                      ),
                    );
                  },
                ),
        ),
      ),
    );
  }
}
