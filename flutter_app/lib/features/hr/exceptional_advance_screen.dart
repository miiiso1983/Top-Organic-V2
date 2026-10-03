import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';

/// شاشة طلب سلفة استثنائية ضمن وحدة الموارد البشرية
class ExceptionalAdvanceScreen extends StatefulWidget {
  const ExceptionalAdvanceScreen({super.key});

  @override
  State<ExceptionalAdvanceScreen> createState() =>
      _ExceptionalAdvanceScreenState();
}

class _ExceptionalAdvanceScreenState extends State<ExceptionalAdvanceScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _template;
  List<dynamic> _employees = [];
  final _formKey = GlobalKey<FormState>();
  final _searchController = TextEditingController();
  final _employeeController = TextEditingController();

  final Map<String, dynamic> _values = {
    'advance_type': 'استثنائية',
    'installments': '1',
  };

  Map<String, dynamic>? _advanceLimit;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _loadTemplate();
  }

  Future<void> _loadTemplate() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<ApiClient>();
      final res = await api.get('/workflows/templates');
      final body = res.data as Map<String, dynamic>;
      final all = (body['data'] as List?) ?? [];
      final template = all.cast<Map<String, dynamic>>().firstWhere(
        (t) => t['code'] == 'hr_exceptional_advance',
        orElse: () => <String, dynamic>{},
      );
      if (template.isEmpty) {
        setState(() {
          _error = AppStrings.t(
            'hr_advance_template_unavailable',
            context.read<LocaleProvider>().locale.languageCode,
          );
          _loading = false;
        });
        return;
      }
      final detailRes = await api.get('/workflows/templates/${template['id']}');
      final detail = detailRes.data as Map<String, dynamic>;
      final data = detail['data'] as Map<String, dynamic>;
      setState(() {
        _template = data;
        _employees = data['employees'] ?? [];
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() {
        _error =
            '${AppStrings.t('hr_advance_load_error_prefix', lang)}${e.toString()}';
        _loading = false;
      });
    }
  }

  Future<void> _onEmployeeChanged(int? employeeId, String? employeeName) async {
    if (employeeId == null) {
      setState(() {
        _values['employee_id'] = null;
        _values['employee_name'] = null;
        _advanceLimit = null;
        _employeeController.text = '';
      });
      return;
    }
    setState(() {
      _values['employee_id'] = employeeId.toString();
      _values['employee_name'] = employeeName;
      _advanceLimit = null;
      _employeeController.text = employeeName ?? '';
    });
    try {
      final api = context.read<ApiClient>();
      final res = await api.get('/employees/$employeeId/advance-limit');
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      setState(() {
        _advanceLimit = body['data'] as Map<String, dynamic>?;
      });
    } catch (e) {
      // ignore: generic fallback message shown when no limit
    }
  }

  Future<void> _submit() async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    if (!_formKey.currentState!.validate()) return;
    _formKey.currentState!.save();

    final limit = _advanceLimit;
    final salary = (limit?['salary'] as num?)?.toDouble() ?? 0.0;
    if (salary <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            limit?['error']?.toString() ?? t('hr_advance_no_salary_found'),
          ),
        ),
      );
      return;
    }

    final amount = double.tryParse(_values['amount']?.toString() ?? '') ?? 0.0;
    final remaining = (limit?['remaining'] as num?)?.toDouble() ?? 0.0;
    if (amount > remaining) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${t('hr_advance_amount_exceeds_prefix')}$remaining'),
        ),
      );
      return;
    }

    setState(() => _submitting = true);
    try {
      final api = context.read<ApiClient>();
      final res = await api.post(
        '/workflows/requests',
        data: {
          'template_id': _template!['id'],
          'title': _template!['name']?.toString() ?? t('hr_advance_title'),
          'fields': _values,
        },
      );
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      if (body['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${t('hr_advance_submitted_prefix')}${body['request_no'] ?? ''}',
            ),
          ),
        );
        context.go('/hr');
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              body['error']?.toString() ?? t('hr_advance_submit_failed'),
            ),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${t('hr_advance_submit_error_prefix')}${e.toString()}',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Widget _buildEmployeeSelector() {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return TextFormField(
      readOnly: true,
      controller: _employeeController,
      decoration: InputDecoration(labelText: t('hr_advance_employee_label')),
      validator: (_) =>
          _values['employee_id'] == null ? t('required_field') : null,
      onTap: _showEmployeePicker,
    );
  }

  void _showEmployeePicker() {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final all = List<Map<String, dynamic>>.from(
      _employees.cast<Map<String, dynamic>>(),
    );
    List<Map<String, dynamic>> filtered = List.from(all);
    _searchController.clear();
    showDialog(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              title: Text(t('hr_advance_choose_employee')),
              content: SizedBox(
                width: double.maxFinite,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    TextField(
                      controller: _searchController,
                      decoration: InputDecoration(
                        hintText: t('hr_advance_search_by_name'),
                      ),
                      onChanged: (v) {
                        setDialogState(() {
                          filtered = all.where((e) {
                            final name = (e['full_name'] ?? '')
                                .toString()
                                .toLowerCase();
                            return name.contains(v.toLowerCase());
                          }).toList();
                        });
                      },
                    ),
                    const SizedBox(height: 8),
                    SizedBox(
                      height: 300,
                      child: ListView.builder(
                        itemCount: filtered.length,
                        itemBuilder: (context, i) {
                          final e = filtered[i];
                          return ListTile(
                            title: Text(e['full_name']?.toString() ?? ''),
                            onTap: () {
                              _onEmployeeChanged(
                                e['id'] as int,
                                e['full_name']?.toString(),
                              );
                              Navigator.of(ctx).pop();
                            },
                          );
                        },
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildAdvanceTypeSelector() {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return DropdownButtonFormField<String>(
      initialValue: _values['advance_type']?.toString(),
      decoration: InputDecoration(labelText: t('hr_advance_type_label')),
      isExpanded: true,
      items: [
        DropdownMenuItem(
          value: 'استثنائية',
          child: Text(t('hr_advance_type_exceptional')),
        ),
        DropdownMenuItem(
          value: 'طويلة الأجل',
          child: Text(t('hr_advance_type_long_term')),
        ),
      ],
      onChanged: (v) => setState(() => _values['advance_type'] = v),
      validator: (v) => v == null ? t('required_field') : null,
      onSaved: (v) => _values['advance_type'] = v,
    );
  }

  Widget _buildInstallmentsField() {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);

    return TextFormField(
      decoration: InputDecoration(
        labelText: t('hr_advance_installments_label'),
      ),
      keyboardType: TextInputType.number,
      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
      initialValue: _values['installments']?.toString(),
      onSaved: (v) => _values['installments'] = v,
      validator: (v) => v == null || v.isEmpty ? t('required_field') : null,
    );
  }

  Widget _buildAmountField() {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final limit = _advanceLimit;
    final salary = (limit?['salary'] as num?)?.toDouble() ?? 0.0;
    final max = (limit?['max'] as num?)?.toDouble() ?? 0.0;
    final remaining = (limit?['remaining'] as num?)?.toDouble() ?? 0.0;
    final error = limit?['error'] as String?;
    final helperText = salary > 0
        ? '${t('hr_advance_salary_label')}$salary${t('hr_advance_max_label')}$max${t('hr_advance_remaining_label')}$remaining'
        : (error ?? t('hr_advance_select_employee_hint'));
    return TextFormField(
      decoration: InputDecoration(
        labelText: t('hr_advance_amount_label'),
        helperText: helperText,
      ),
      keyboardType: TextInputType.number,
      onSaved: (v) => _values['amount'] = v,
      validator: (v) {
        if (v == null || v.isEmpty) return t('required_field');
        final val = double.tryParse(v) ?? 0.0;
        if (val <= 0) return t('hr_advance_amount_invalid');
        if (salary > 0 && val > remaining)
          return '${t('hr_advance_amount_exceeds_prefix')}$remaining';
        return null;
      },
    );
  }

  Widget _buildReasonField() {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return TextFormField(
      decoration: InputDecoration(labelText: t('hr_advance_reason_label')),
      maxLines: 3,
      onSaved: (v) => _values['reason'] = v,
      validator: (v) => v == null || v.isEmpty ? t('required_field') : null,
    );
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(title: Text(t('hr_advance_title'))),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? Center(child: Text(_error!))
          : Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  _buildEmployeeSelector(),
                  const SizedBox(height: 16),
                  _buildAdvanceTypeSelector(),
                  const SizedBox(height: 16),
                  _buildInstallmentsField(),
                  const SizedBox(height: 16),
                  _buildAmountField(),
                  const SizedBox(height: 16),
                  _buildReasonField(),
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
                          ? t('hr_advance_submitting')
                          : t('hr_advance_submit_button'),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
