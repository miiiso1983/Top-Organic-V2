import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';

class OvertimeManagerScreen extends StatefulWidget {
  const OvertimeManagerScreen({super.key});

  @override
  State<OvertimeManagerScreen> createState() => _OvertimeManagerScreenState();
}

class _OvertimeManagerScreenState extends State<OvertimeManagerScreen> {
  bool _loading = true;
  String? _error;
  List<dynamic> _requests = [];
  final Map<int, bool> _processing = {};

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
      final res = await api.get('/overtime/pending');
      final body = res.data as Map<String, dynamic>;
      setState(() {
        _requests = (body['data'] as List?) ?? [];
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() {
        _error =
            '${AppStrings.t('hr_overtime_manager_load_error_prefix', lang)}${e.toString()}';
        _loading = false;
      });
    }
  }

  Future<void> _decide(int id, String decision) async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    setState(() => _processing[id] = true);
    try {
      final api = context.read<ApiClient>();
      await api.post(
        '/overtime/$id/decide',
        data: {'decision': decision, 'notes': ''},
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            decision == 'approved'
                ? t('hr_overtime_manager_approved')
                : t('hr_overtime_manager_rejected'),
          ),
        ),
      );
      _load();
    } catch (e) {
      if (!mounted) return;
      String message = t('hr_overtime_manager_decide_error');
      if (e is Exception && e.toString().isNotEmpty) message = e.toString();
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _processing.remove(id));
    }
  }

  String _statusLabel(String status, String Function(String) t) {
    return {
          'pending': t('hr_overtime_manager_status_pending'),
          'manager_approved': t('hr_overtime_manager_status_manager_approved'),
          'fully_approved': t('hr_overtime_manager_status_fully_approved'),
          'rejected': t('hr_overtime_manager_status_rejected'),
          'expired': t('hr_overtime_manager_status_expired'),
        }[status] ??
        status;
  }

  String _attendanceText(List<dynamic> attendance, String Function(String) t) {
    if (attendance.isEmpty) return t('hr_overtime_manager_no_attendance');
    return attendance
        .map((a) {
          final m = a as Map<String, dynamic>;
          return '${m['check_type'] == 'in' ? t('hr_overtime_manager_check_in_word') : t('hr_overtime_manager_check_out_word')} ${m['checked_at']?.toString() ?? ''}';
        })
        .join(' / ');
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(title: Text(t('hr_overtime_manager_title'))),
      body: AsyncStateView(
        loading: _loading,
        error: _error,
        onRetry: _load,
        child: RefreshIndicator(
          onRefresh: _load,
          child: _requests.isEmpty
              ? Center(child: Text(t('hr_overtime_manager_no_pending')))
              : ListView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: _requests.length,
                  itemBuilder: (context, i) {
                    final m = _requests[i] as Map<String, dynamic>;
                    final id = m['id'] as int;
                    final matched =
                        (m['biometric_match'] == 1 ||
                        m['biometric_match'] == true);
                    final canDecide = m['can_decide'] == true;
                    final processing = _processing[id] == true;
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              m['full_name']?.toString() ?? '',
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Text(
                              '${_statusLabel(m['status']?.toString() ?? '', t)} • ${m['step_title']?.toString() ?? ''}',
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${t('hr_overtime_manager_date_label')}${m['request_date']}${t('hr_overtime_manager_hours_requested_label')}${m['hours']}${t('hr_overtime_manager_amount_label')}${m['amount']?.toString() ?? ''}',
                            ),
                            Text(
                              '${t('hr_overtime_manager_actual_hours_label')}${m['biometric_hours']?.toString() ?? ''}',
                            ),
                            Text(
                              '${t('hr_overtime_manager_biometric_label')}${_attendanceText((m['attendance'] as List?) ?? [], t)}',
                              style: const TextStyle(fontSize: 12),
                            ),
                            const SizedBox(height: 8),
                            Row(
                              children: [
                                Chip(
                                  label: Text(
                                    matched
                                        ? t('hr_overtime_manager_matched')
                                        : t('hr_overtime_manager_not_matched'),
                                  ),
                                  backgroundColor: matched
                                      ? Colors.green[100]
                                      : Colors.orange[100],
                                ),
                                const Spacer(),
                                if (canDecide && !processing) ...[
                                  OutlinedButton(
                                    onPressed: () => _decide(id, 'rejected'),
                                    child: Text(
                                      t('hr_overtime_manager_reject_button'),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  ElevatedButton(
                                    onPressed: () => _decide(id, 'approved'),
                                    child: Text(
                                      t('hr_overtime_manager_approve_button'),
                                    ),
                                  ),
                                ],
                                if (processing)
                                  const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  ),
                              ],
                            ),
                          ],
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
