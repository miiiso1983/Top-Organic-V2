import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';

/// تفاصيل كشف راتب واحد: كل بنود الاستحقاقات والاستقطاعات
class SalaryDetailScreen extends StatefulWidget {
  const SalaryDetailScreen({
    super.key,
    required this.payrollId,
    required this.period,
  });
  final int payrollId;
  final String period;

  @override
  State<SalaryDetailScreen> createState() => _SalaryDetailScreenState();
}

class _SalaryDetailScreenState extends State<SalaryDetailScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _payroll;
  List<dynamic> _overtime = [];
  List<dynamic> _allowances = [];

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
      final res = await api.get('/payroll/${widget.payrollId}');
      final body = res.data as Map<String, dynamic>;
      setState(() {
        _payroll = (body['data'] as Map?)?.cast<String, dynamic>();
        _overtime = (body['overtime'] as List?) ?? [];
        _allowances = (body['allowances'] as List?) ?? [];
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() {
        _error =
            '${AppStrings.t('hr_salary_detail_load_error_prefix', lang)}${e.toString()}';
        _loading = false;
      });
    }
  }

  /// تحويل آمن للقيم القادمة من الخادم (قد تصل كنص أو رقم) إلى عدد
  num _toNum(dynamic v) {
    if (v is num) return v;
    return num.tryParse(v?.toString() ?? '') ?? 0;
  }

  num _n(String key) => _toNum(_payroll?[key]);

  Widget _row(String label, num value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: bold ? const TextStyle(fontWeight: FontWeight.bold) : null,
          ),
          Text(
            value.toString(),
            style: bold ? const TextStyle(fontWeight: FontWeight.bold) : null,
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(
        title: Text('${t('hr_salary_detail_title_prefix')}${widget.period}'),
      ),
      body: AsyncStateView(
        loading: _loading,
        error: _error,
        onRetry: _load,
        child: _payroll == null
            ? Center(child: Text(t('hr_salary_detail_no_data')))
            : ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text(
                    t('hr_salary_detail_earnings'),
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                    ),
                  ),
                  const Divider(),
                  _row(t('hr_salary_detail_base_salary'), _n('base_salary')),
                  _row(t('hr_salary_detail_raises'), _n('raises')),
                  ..._allowances.map((a) {
                    final m = a as Map<String, dynamic>;
                    return _row(
                      m['allowance_type']?.toString() ??
                          t('hr_salary_detail_allowance_fallback'),
                      _toNum(m['amount']),
                    );
                  }),
                  _row(t('hr_salary_detail_bonuses'), _n('bonuses')),
                  _row(
                    t('hr_salary_detail_travel_tickets'),
                    _n('travel_tickets'),
                  ),
                  ..._overtime.map((o) {
                    final m = o as Map<String, dynamic>;
                    return _row(
                      '${t('hr_salary_detail_overtime_prefix')}${m['hours']}${t('hr_salary_detail_overtime_hours_suffix')}${m['multiplier']}',
                      _toNum(m['amount']),
                    );
                  }),
                  _row(
                    t('hr_salary_detail_tips_addition'),
                    _n('tips_addition'),
                  ),
                  _row(
                    t('hr_salary_detail_total_earnings'),
                    _n('total_earnings'),
                    bold: true,
                  ),
                  const SizedBox(height: 16),
                  Text(
                    t('hr_salary_detail_deductions'),
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                    ),
                  ),
                  const Divider(),
                  _row(t('hr_salary_detail_deductions_row'), _n('deductions')),
                  _row(t('hr_salary_detail_penalties'), _n('penalties')),
                  _row(t('hr_salary_detail_advances'), _n('advances')),
                  _row(
                    t('hr_salary_detail_long_term_advances'),
                    _n('long_term_advances'),
                  ),
                  _row(
                    t('hr_salary_detail_security_deposit'),
                    _n('security_deposit'),
                  ),
                  _row(t('hr_salary_detail_insurance'), _n('insurance')),
                  _row(t('hr_salary_detail_loadings'), _n('loadings')),
                  _row(t('hr_salary_detail_tips'), _n('tips')),
                  _row(
                    t('hr_salary_detail_social_security'),
                    _n('social_security'),
                  ),
                  _row(
                    t('hr_salary_detail_housing_deduction'),
                    _n('housing_deduction'),
                  ),
                  _row(
                    '${t('hr_salary_detail_absence_prefix')}${_n('absence_days')}${t('hr_salary_detail_absence_suffix')}',
                    _n('absence_deduction'),
                  ),
                  _row(t('hr_salary_detail_tax'), _n('tax_amount')),
                  _row(
                    t('hr_salary_detail_total_deductions'),
                    _n('total_deductions'),
                    bold: true,
                  ),
                  const SizedBox(height: 16),
                  const Divider(thickness: 2),
                  _row(
                    t('hr_salary_detail_net_salary'),
                    _n('net_salary'),
                    bold: true,
                  ),
                ],
              ),
      ),
    );
  }
}
