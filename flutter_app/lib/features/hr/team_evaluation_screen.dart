import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';
import 'team_evaluation_form_screen.dart';
import 'team_evaluation_detail_screen.dart';
import 'team_request_screen.dart';

/// شاشة تقييم أداء الفريق للمدير — تعرض المرؤوسين حسب الهيكل الإداري (شجرة التقييم)
class TeamEvaluationScreen extends StatefulWidget {
  const TeamEvaluationScreen({super.key});

  @override
  State<TeamEvaluationScreen> createState() => _TeamEvaluationScreenState();
}

class _TeamEvaluationScreenState extends State<TeamEvaluationScreen> {
  bool _loading = true;
  String? _error;
  List<dynamic> _team = [];
  late String _period;
  int _loadSequence = 0;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _period =
        '${now.year.toString().padLeft(4, '0')}-${now.month.toString().padLeft(2, '0')}';
    _load();
  }

  Future<void> _load() async {
    final sequence = ++_loadSequence;
    final period = _period;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<ApiClient>();
      final res = await api.get('/team/evaluations', query: {'period': period});
      final body = res.data as Map<String, dynamic>;
      if (!mounted || sequence != _loadSequence) return;
      setState(() {
        _team = (body['data'] as List?) ?? [];
        _loading = false;
      });
    } catch (e) {
      if (!mounted || sequence != _loadSequence) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() {
        _error =
            '${AppStrings.t('hr_eval_load_error_prefix', lang)}${e.toString()}';
        _loading = false;
      });
    }
  }

  void _changeMonth(int delta) {
    final parts = _period.split('-');
    var year = int.parse(parts[0]);
    var month = int.parse(parts[1]) + delta;
    if (month < 1) {
      month = 12;
      year -= 1;
    }
    if (month > 12) {
      month = 1;
      year += 1;
    }
    setState(
      () => _period =
          '${year.toString().padLeft(4, '0')}-${month.toString().padLeft(2, '0')}',
    );
    _load();
  }

  /// تحويل آمن للقيم القادمة من الخادم (قد تصل كنص أو رقم) إلى عدد صحيح
  int _toInt(dynamic v) {
    if (v is int) return v;
    if (v is num) return v.toInt();
    return int.tryParse(v?.toString() ?? '') ?? 0;
  }

  Future<void> _openForm(Map<String, dynamic> member) async {
    final done = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => TeamEvaluationFormScreen(
          employeeId: _toInt(member['id']),
          employeeName: member['full_name']?.toString() ?? '',
          period: _period,
        ),
      ),
    );
    if (done == true) _load();
  }

  void _openDetail(Map<String, dynamic> member) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TeamEvaluationDetailScreen(
          evaluationId: _toInt(member['evaluation_id']),
        ),
      ),
    );
  }

  void _openRequestOnBehalf(Map<String, dynamic> member) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TeamRequestScreen(
          employeeId: _toInt(member['id']),
          employeeName: member['full_name']?.toString() ?? '',
        ),
      ),
    );
  }

  /// يعرض قائمة خيارات عند الضغط على اسم أحد أعضاء الفريق:
  /// تقييم الموظف (أو عرض التقييم إن كان مُقيَّماً) وتقديم طلب نيابةً عنه
  void _showMemberOptions(Map<String, dynamic> member) {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final evaluated = member['evaluation_id'] != null;
    showModalBottomSheet(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: Text(
                member['full_name']?.toString() ?? '',
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                ),
              ),
            ),
            ListTile(
              leading: Icon(evaluated ? Icons.visibility : Icons.fact_check),
              title: Text(
                evaluated
                    ? t('hr_eval_view_button')
                    : t('hr_team_options_evaluate'),
              ),
              onTap: () {
                Navigator.of(ctx).pop();
                evaluated ? _openDetail(member) : _openForm(member);
              },
            ),
            ListTile(
              leading: const Icon(Icons.assignment_ind_outlined),
              title: Text(t('hr_team_options_request_on_behalf')),
              onTap: () {
                Navigator.of(ctx).pop();
                _openRequestOnBehalf(member);
              },
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(title: Text(t('hr_eval_title'))),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                IconButton(
                  icon: const Icon(Icons.chevron_right),
                  onPressed: () => _changeMonth(-1),
                ),
                Flexible(
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(
                      _period,
                      textDirection: TextDirection.ltr,
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.chevron_left),
                  onPressed: () => _changeMonth(1),
                ),
              ],
            ),
          ),
          Expanded(
            child: AsyncStateView(
              loading: _loading,
              error: _error,
              onRetry: _load,
              child: RefreshIndicator(
                onRefresh: _load,
                child: _team.isEmpty
                    ? ListView(
                        children: [
                          Padding(
                            padding: const EdgeInsets.all(32),
                            child: Center(
                              child: Text(t('hr_eval_no_subordinates')),
                            ),
                          ),
                        ],
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.all(12),
                        itemCount: _team.length,
                        itemBuilder: (context, i) {
                          final m = _team[i] as Map<String, dynamic>;
                          final evaluated = m['evaluation_id'] != null;
                          final score = m['total_score'];
                          final subtitle = [m['job_title'], m['department']]
                              .map((value) => value?.toString().trim() ?? '')
                              .where((value) => value.isNotEmpty)
                              .join(' • ');
                          return Card(
                            clipBehavior: Clip.antiAlias,
                            child: InkWell(
                              onTap: () => _showMemberOptions(m),
                              child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Column(
                                  crossAxisAlignment:
                                      CrossAxisAlignment.stretch,
                                  children: [
                                    Text(
                                      m['full_name']?.toString() ?? '',
                                      style: Theme.of(
                                        context,
                                      ).textTheme.titleMedium,
                                    ),
                                    if (subtitle.isNotEmpty) ...[
                                      const SizedBox(height: 4),
                                      Text(subtitle),
                                    ],
                                    const SizedBox(height: 12),
                                    Wrap(
                                      spacing: 8,
                                      runSpacing: 8,
                                      crossAxisAlignment:
                                          WrapCrossAlignment.center,
                                      children: [
                                        Container(
                                          padding: const EdgeInsets.symmetric(
                                            horizontal: 12,
                                            vertical: 8,
                                          ),
                                          decoration: BoxDecoration(
                                            color: evaluated
                                                ? Colors.green[100]
                                                : Colors.orange[100],
                                            borderRadius: BorderRadius.circular(
                                              12,
                                            ),
                                          ),
                                          child: Text(
                                            evaluated
                                                ? '${t('hr_eval_evaluated_prefix')}${score ?? 0}${t('hr_eval_evaluated_suffix')}'
                                                : t('hr_eval_pending'),
                                            style: TextStyle(
                                              color: evaluated
                                                  ? Colors.green[900]
                                                  : Colors.brown[900],
                                            ),
                                          ),
                                        ),
                                        TextButton.icon(
                                          onPressed: () => evaluated
                                              ? _openDetail(m)
                                              : _openForm(m),
                                          icon: Icon(
                                            evaluated
                                                ? Icons.visibility_outlined
                                                : Icons.fact_check_outlined,
                                          ),
                                          label: Text(
                                            evaluated
                                                ? t('hr_eval_view_button')
                                                : t('hr_team_options_evaluate'),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          );
                        },
                      ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
