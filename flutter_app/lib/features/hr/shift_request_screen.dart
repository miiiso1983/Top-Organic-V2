import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../core/network/api_client.dart';
import '../../features/auth/auth_provider.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';

/// شاشة طلب تغيير وردية من قبل الموظف
class ShiftRequestScreen extends StatefulWidget {
  const ShiftRequestScreen({super.key});

  @override
  State<ShiftRequestScreen> createState() => _ShiftRequestScreenState();
}

class _ShiftRequestScreenState extends State<ShiftRequestScreen> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _shifts = [];
  int? _templateId;
  final _formKey = GlobalKey<FormState>();
  final Map<String, dynamic> _values = {};
  bool _submitting = false;

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
      final shiftsRes = await api.get('/shifts');
      final tplRes = await api.get('/workflows/templates');
      final tplList = ((tplRes.data as Map)['data'] as List?) ?? [];
      final tpl = tplList.cast<Map<String, dynamic>>().firstWhere(
        (t) => t['code'] == 'hr_shift_change',
        orElse: () => <String, dynamic>{},
      );
      if (!mounted) return;
      setState(() {
        _shifts = List<Map<String, dynamic>>.from(
          (shiftsRes.data as Map)['data'] ?? [],
        );
        _templateId = tpl['id'] as int?;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() {
        _error =
            '${AppStrings.t('hr_shift_request_load_error_prefix', lang)}${e.toString()}';
        _loading = false;
      });
    }
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: now.add(const Duration(days: 1)),
      firstDate: now,
      lastDate: now.add(const Duration(days: 90)),
    );
    if (picked != null)
      setState(
        () => _values['shift_date'] = DateFormat('yyyy-MM-dd').format(picked),
      );
  }

  Future<void> _submit() async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    if (!_formKey.currentState!.validate()) return;
    _formKey.currentState!.save();
    final user = context.read<AuthProvider>().user;
    if ((user?['employee_id'] as int?) == null ||
        _templateId == null ||
        _values['shift_date'] == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('hr_shift_request_missing_data'))),
      );
      return;
    }
    setState(() => _submitting = true);
    try {
      final api = context.read<ApiClient>();
      final res = await api.post(
        '/workflows/requests',
        data: {
          'template_id': _templateId,
          'title': t('hr_shift_request_title'),
          'fields': {
            'employee_id': user!['employee_id'].toString(),
            'employee_name': user['employee_name']?.toString(),
            'shift_id': _values['shift_id']?.toString(),
            'shift_name': _values['shift_name']?.toString(),
            'shift_date': _values['shift_date'],
            'reason': _values['reason']?.toString(),
          },
        },
      );
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      if (body['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${t('hr_shift_request_submitted_prefix')}${body['request_no'] ?? ''}',
            ),
          ),
        );
        context.go('/hr');
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              body['error']?.toString() ?? t('hr_shift_request_submit_failed'),
            ),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${t('hr_shift_request_submit_error_prefix')}$e'),
        ),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(title: Text(t('hr_shift_request_title'))),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? Center(child: Text(_error!))
          : Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  InkWell(
                    onTap: _pickDate,
                    child: InputDecorator(
                      decoration: InputDecoration(
                        labelText: t('hr_shift_request_date_label'),
                      ),
                      child: Text(
                        _values['shift_date']?.toString() ??
                            t('hr_shift_request_pick_date'),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<int>(
                    decoration: InputDecoration(
                      labelText: t('hr_shift_request_shift_label'),
                    ),
                    isExpanded: true,
                    items: _shifts
                        .map(
                          (s) => DropdownMenuItem<int>(
                            value: s['id'] as int,
                            child: Text(
                              '${s['name']} (${s['start_time']} - ${s['end_time']})',
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: (v) => setState(() {
                      _values['shift_id'] = v;
                      _values['shift_name'] = _shifts.firstWhere(
                        (s) => s['id'] == v,
                      )['name'];
                    }),
                    validator: (v) => v == null ? t('required_field') : null,
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    decoration: InputDecoration(
                      labelText: t('hr_shift_request_reason_label'),
                    ),
                    maxLines: 3,
                    onSaved: (v) => _values['reason'] = v,
                    validator: (v) =>
                        v == null || v.isEmpty ? t('required_field') : null,
                  ),
                  const SizedBox(height: 24),
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
                        : const Icon(Icons.send),
                    label: Text(
                      _submitting
                          ? t('hr_shift_request_submitting')
                          : t('hr_shift_request_submit_button'),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
