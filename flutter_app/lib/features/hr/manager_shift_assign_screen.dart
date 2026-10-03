import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart' hide TextDirection;
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';

/// شاشة المدير لتعيين وردية محددة ليوم معين لأحد مرؤوسيه
class ManagerShiftAssignScreen extends StatefulWidget {
  const ManagerShiftAssignScreen({super.key});

  @override
  State<ManagerShiftAssignScreen> createState() =>
      _ManagerShiftAssignScreenState();
}

class _ManagerShiftAssignScreenState extends State<ManagerShiftAssignScreen> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _employees = [];
  List<Map<String, dynamic>> _shifts = [];
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
      final empRes = await api.get('/employees', query: {'subordinates': '1'});
      final shiftRes = await api.get('/shifts');
      if (!mounted) return;
      setState(() {
        _employees = List<Map<String, dynamic>>.from(
          (empRes.data as Map)['data'] ?? [],
        );
        _shifts = List<Map<String, dynamic>>.from(
          (shiftRes.data as Map)['data'] ?? [],
        );
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() {
        _error =
            '${AppStrings.t('hr_shift_assign_load_error_prefix', lang)}${e.toString()}';
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
    if (picked != null) {
      setState(
        () => _values['shift_date'] = DateFormat('yyyy-MM-dd').format(picked),
      );
    }
  }

  Future<void> _submit() async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    if (!_formKey.currentState!.validate()) return;
    final employeeId = _values['employee_id'] as int?;
    final shiftId = _values['shift_id'] as int?;
    final shiftDate = _values['shift_date']?.toString();
    if (employeeId == null ||
        shiftId == null ||
        shiftDate == null ||
        shiftDate.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('hr_shift_assign_fill_all_fields'))),
      );
      return;
    }
    setState(() => _submitting = true);
    try {
      final api = context.read<ApiClient>();
      final res = await api.post(
        '/employee-shifts',
        data: {
          'employee_id': employeeId,
          'shift_id': shiftId,
          'shift_date': shiftDate,
        },
      );
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      if (body['ok'] == true) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(t('hr_shift_assign_success'))));
        context.go('/hr');
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              body['error']?.toString() ?? t('hr_shift_assign_failed'),
            ),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${t('hr_shift_assign_error_prefix')}${e.toString()}'),
        ),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  /// حقل بحث منسدل لاختيار الموظف من قائمة المرؤوسين (يفتح قائمة قابلة للبحث بالاسم أو الرقم)
  Widget _buildEmployeeSearchField() {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    String displayFor(Map<String, dynamic> e) =>
        e['full_name']?.toString() ?? '';
    final selectedId = _values['employee_id'] as int?;
    final selected = _employees.firstWhere(
      (e) => e['id'] == selectedId,
      orElse: () => const <String, dynamic>{},
    );
    final textController = TextEditingController(
      text: selected.isEmpty ? '' : displayFor(selected),
    );

    return TextFormField(
      controller: textController,
      readOnly: true,
      decoration: InputDecoration(
        labelText: t('hr_shift_assign_employee_label'),
        suffixIcon: const Icon(Icons.arrow_drop_down),
      ),
      validator: (v) =>
          _values['employee_id'] == null ? t('required_field') : null,
      onTap: () async {
        final queryController = TextEditingController();
        final picked = await showModalBottomSheet<Map<String, dynamic>>(
          context: context,
          isScrollControlled: true,
          builder: (ctx) {
            return StatefulBuilder(
              builder: (ctx, setModalState) {
                final query = queryController.text.trim().toLowerCase();
                final filtered = query.isEmpty
                    ? _employees
                    : _employees.where((e) {
                        final name = displayFor(e).toLowerCase();
                        return name.contains(query);
                      }).toList();
                return Directionality(
                  textDirection: TextDirection.rtl,
                  child: Padding(
                    padding: EdgeInsets.only(
                      bottom: MediaQuery.of(ctx).viewInsets.bottom,
                    ),
                    child: SizedBox(
                      height: MediaQuery.of(ctx).size.height * 0.65,
                      child: Column(
                        children: [
                          Padding(
                            padding: const EdgeInsets.all(16),
                            child: Text(
                              t('hr_shift_assign_choose_employee'),
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                              ),
                            ),
                          ),
                          Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            child: TextField(
                              controller: queryController,
                              textDirection: TextDirection.rtl,
                              decoration: InputDecoration(
                                prefixIcon: const Icon(Icons.search),
                                hintText: t('hr_shift_assign_search_hint'),
                                border: const OutlineInputBorder(),
                                contentPadding: const EdgeInsets.symmetric(
                                  horizontal: 12,
                                  vertical: 14,
                                ),
                              ),
                              onChanged: (_) => setModalState(() {}),
                            ),
                          ),
                          const SizedBox(height: 8),
                          Expanded(
                            child: filtered.isEmpty
                                ? Center(
                                    child: Text(
                                      t('hr_shift_assign_no_results'),
                                    ),
                                  )
                                : ListView.builder(
                                    itemCount: filtered.length,
                                    itemBuilder: (ctx, i) {
                                      final e = filtered[i];
                                      final isSelected = e['id'] == selectedId;
                                      return ListTile(
                                        title: Text(
                                          displayFor(e),
                                          textDirection: TextDirection.rtl,
                                          textAlign: TextAlign.right,
                                        ),
                                        trailing: isSelected
                                            ? const Icon(
                                                Icons.check,
                                                color: Color(0xFF2E7D32),
                                              )
                                            : null,
                                        onTap: () => Navigator.pop(ctx, e),
                                      );
                                    },
                                  ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            );
          },
        );
        if (picked != null) {
          setState(() => _values['employee_id'] = picked['id'] as int);
        }
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(title: Text(t('hr_shift_assign_title'))),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? Center(child: Text(_error!))
          : _employees.isEmpty
          ? Center(child: Text(t('hr_shift_assign_no_subordinates')))
          : Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  _buildEmployeeSearchField(),
                  const SizedBox(height: 16),
                  InkWell(
                    onTap: _pickDate,
                    child: InputDecorator(
                      decoration: InputDecoration(
                        labelText: t('hr_shift_assign_date_label'),
                      ),
                      child: Text(
                        _values['shift_date']?.toString() ??
                            t('hr_shift_assign_pick_date'),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<int>(
                    decoration: InputDecoration(
                      labelText: t('hr_shift_assign_shift_label'),
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
                    onChanged: (v) => setState(() => _values['shift_id'] = v),
                    validator: (v) => v == null ? t('required_field') : null,
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
                        : const Icon(Icons.save),
                    label: Text(
                      _submitting
                          ? t('hr_shift_assign_saving')
                          : t('hr_shift_assign_save_button'),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
