import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';

/// عرض تفاصيل تقييم أداء سابق لمرؤوس (القراءة فقط)
class TeamEvaluationDetailScreen extends StatefulWidget {
  const TeamEvaluationDetailScreen({super.key, required this.evaluationId});

  final int evaluationId;

  @override
  State<TeamEvaluationDetailScreen> createState() =>
      _TeamEvaluationDetailScreenState();
}

class _TeamEvaluationDetailScreenState
    extends State<TeamEvaluationDetailScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _eval;
  List<dynamic> _items = [];

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
      final res = await api.get('/team/evaluations/${widget.evaluationId}');
      final body = res.data as Map<String, dynamic>;
      setState(() {
        _eval = (body['data'] as Map?)?.cast<String, dynamic>();
        _items = (body['items'] as List?) ?? [];
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() {
        _error =
            '${AppStrings.t('hr_eval_detail_load_error_prefix', lang)}${e.toString()}';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(title: Text(t('hr_eval_detail_title'))),
      body: AsyncStateView(
        loading: _loading,
        error: _error,
        onRetry: _load,
        child: _eval == null
            ? const SizedBox.shrink()
            : ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _eval!['full_name']?.toString() ?? '',
                            style: const TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 18,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '${_eval!['job_title'] ?? ''} • ${_eval!['department'] ?? ''}',
                            style: const TextStyle(color: Colors.grey),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '${t('hr_eval_detail_period_prefix')}${_eval!['period'] ?? ''}',
                          ),
                          const SizedBox(height: 12),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Flexible(
                                child: Chip(
                                  label: Text(
                                    '${t('hr_eval_detail_total_score_prefix')}${_eval!['total_score'] ?? 0}',
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  backgroundColor: Colors.green[100],
                                ),
                              ),
                              const SizedBox(width: 8),
                              Flexible(
                                child: Text(
                                  _eval!['status'] == 'finalized'
                                      ? t('hr_eval_detail_status_finalized')
                                      : t('hr_eval_detail_status_draft'),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ],
                          ),
                          if ((_eval!['notes'] ?? '')
                              .toString()
                              .isNotEmpty) ...[
                            const SizedBox(height: 12),
                            Text(
                              t('hr_eval_detail_notes_label'),
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Text(_eval!['notes'].toString()),
                          ],
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    t('hr_eval_detail_items_title'),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  ..._items.map((it) {
                    final m = it as Map<String, dynamic>;
                    return Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        title: Text(m['kpi_name']?.toString() ?? ''),
                        subtitle: (m['notes'] ?? '').toString().isNotEmpty
                            ? Text(m['notes'].toString())
                            : null,
                        trailing: Column(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(
                              '${m['score'] ?? 0}',
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Text(
                              '${t('hr_eval_form_weight_prefix')}${m['weight'] ?? 0}%',
                              style: const TextStyle(
                                fontSize: 11,
                                color: Colors.grey,
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  }),
                ],
              ),
      ),
    );
  }
}
