import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';

/// نموذج تقييم مؤشرات الأداء (KPI) لمرؤوس معيّن عن فترة محددة
class TeamEvaluationFormScreen extends StatefulWidget {
  const TeamEvaluationFormScreen({
    super.key,
    required this.employeeId,
    required this.employeeName,
    required this.period,
  });

  final int employeeId;
  final String employeeName;
  final String period;

  @override
  State<TeamEvaluationFormScreen> createState() =>
      _TeamEvaluationFormScreenState();
}

class _TeamEvaluationFormScreenState extends State<TeamEvaluationFormScreen> {
  bool _loading = true;
  bool _submitting = false;
  String? _error;
  List<dynamic> _kpis = [];
  bool _alreadyEvaluated = false;
  final Map<int, double> _scores = {};
  final Map<int, TextEditingController> _notesControllers = {};
  final _overallNotesController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final c in _notesControllers.values) {
      c.dispose();
    }
    _overallNotesController.dispose();
    super.dispose();
  }

  /// تحويل آمن للقيم القادمة من الخادم (قد تصل كنص أو رقم) إلى عدد صحيح
  int _toInt(dynamic v) {
    if (v is int) return v;
    if (v is num) return v.toInt();
    return int.tryParse(v?.toString() ?? '') ?? 0;
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<ApiClient>();
      final res = await api.get(
        '/team/employees/${widget.employeeId}/kpis',
        query: {'period': widget.period},
      );
      final body = res.data as Map<String, dynamic>;
      final kpis = (body['data'] as List?) ?? [];
      setState(() {
        _kpis = kpis;
        _alreadyEvaluated = body['already_evaluated'] == true;
        for (final k in kpis) {
          final m = k as Map<String, dynamic>;
          final id = _toInt(m['id']);
          _scores[id] = 0;
          _notesControllers[id] = TextEditingController();
        }
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() {
        _error =
            '${AppStrings.t('hr_eval_form_load_error_prefix', lang)}${e.toString()}';
        _loading = false;
      });
    }
  }

  Future<void> _submit() async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    if (_kpis.isEmpty) return;
    setState(() => _submitting = true);
    try {
      final api = context.read<ApiClient>();
      final payload = _kpis.map((k) {
        final m = k as Map<String, dynamic>;
        final id = _toInt(m['id']);
        return {
          'kpi_id': id,
          'score': _scores[id] ?? 0,
          'notes': _notesControllers[id]?.text ?? '',
        };
      }).toList();
      final res = await api.post(
        '/team/evaluations',
        data: {
          'employee_id': widget.employeeId,
          'period': widget.period,
          'kpi_scores': payload,
          'notes': _overallNotesController.text,
        },
      );
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      if (body['ok'] == true) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(t('hr_eval_form_saved'))));
        Navigator.of(context).pop(true);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              body['error']?.toString() ?? t('hr_eval_form_save_failed'),
            ),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      String message = t('hr_eval_form_save_error');
      if (e is DioException && e.response?.data is Map) {
        message = (e.response!.data as Map)['error']?.toString() ?? message;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          '${t('hr_eval_form_title_prefix')}${widget.employeeName} — ${widget.period}',
        ),
      ),
      body: AsyncStateView(
        loading: _loading,
        error: _error,
        onRetry: _load,
        child: _alreadyEvaluated
            ? Center(child: Text(t('hr_eval_form_already_evaluated')))
            : _kpis.isEmpty
            ? Center(child: Text(t('hr_eval_form_no_kpis')))
            : ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  ..._kpis.map((k) {
                    final m = k as Map<String, dynamic>;
                    final id = _toInt(m['id']);
                    return Card(
                      margin: const EdgeInsets.only(bottom: 12),
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              m['name']?.toString() ?? '',
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            if ((m['description'] ?? '').toString().isNotEmpty)
                              Text(
                                m['description'].toString(),
                                style: const TextStyle(
                                  fontSize: 12,
                                  color: Colors.grey,
                                ),
                              ),
                            Text(
                              '${t('hr_eval_form_weight_prefix')}${m['weight'] ?? 0}%',
                              style: const TextStyle(
                                fontSize: 12,
                                color: Colors.grey,
                              ),
                            ),
                            Row(
                              children: [
                                Expanded(
                                  child: Slider(
                                    value: _scores[id] ?? 0,
                                    min: 0,
                                    max: 100,
                                    divisions: 20,
                                    label: (_scores[id] ?? 0)
                                        .round()
                                        .toString(),
                                    onChanged: (v) =>
                                        setState(() => _scores[id] = v),
                                  ),
                                ),
                                SizedBox(
                                  width: 36,
                                  child: Text(
                                    (_scores[id] ?? 0).round().toString(),
                                    textAlign: TextAlign.center,
                                  ),
                                ),
                              ],
                            ),
                            TextField(
                              controller: _notesControllers[id],
                              decoration: InputDecoration(
                                labelText: t('hr_eval_form_notes_optional'),
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  }),
                  TextField(
                    controller: _overallNotesController,
                    decoration: InputDecoration(
                      labelText: t('hr_eval_form_overall_notes_optional'),
                    ),
                    maxLines: 3,
                  ),
                  const SizedBox(height: 16),
                  ElevatedButton.icon(
                    onPressed: _submitting ? null : _submit,
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
                    label: Text(
                      _submitting
                          ? t('hr_eval_form_saving')
                          : t('hr_eval_form_save_button'),
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}
