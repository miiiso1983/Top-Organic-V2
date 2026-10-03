import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../features/auth/auth_provider.dart';
import '../../shared/widgets/offline_banner.dart';

/// شاشة إنشاء طلب موافقة (Workflow) من تطبيق الموبايل
/// تعرض قائمة القوالب المتاحة، ثم نموذج الحقول الديناميكي لكل قالب.
class WorkflowCreateScreen extends StatefulWidget {
  const WorkflowCreateScreen({super.key, this.initialTemplateId});

  /// إذا مُرِّر (عبر القائمة المنسدلة في شاشة الموافقات)، يتم فتح نموذج هذا
  /// القالب مباشرةً دون المرور بشاشة اختيار نوع الطلب.
  final int? initialTemplateId;

  @override
  State<WorkflowCreateScreen> createState() => _WorkflowCreateScreenState();
}

class _WorkflowCreateScreenState extends State<WorkflowCreateScreen> {
  bool _loading = true;
  String? _error;
  List<dynamic> _templates = [];
  Map<String, dynamic>? _selectedTemplate;
  List<dynamic> _employees = [];
  List<dynamic> _departments = [];
  List<Map<String, dynamic>> _items = [];
  Map<String, dynamic>? _advanceLimit;
  bool _autoFillEmployee = false;
  final Map<String, dynamic> _values = {};
  final Map<String, XFile?> _files = {};
  bool _submitting = false;
  final _formKey = GlobalKey<FormState>();

  /// ترجمة نص حسب اللغة الحالية، قابلة للاستخدام خارج build() أيضاً
  String t(String key) =>
      AppStrings.t(key, context.read<LocaleProvider>().locale.languageCode);

  @override
  void initState() {
    super.initState();
    if (widget.initialTemplateId != null) {
      _selectTemplate(widget.initialTemplateId!);
    } else {
      _loadTemplates();
    }
  }

  Future<void> _loadTemplates() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<ApiClient>();
      final res = await api.get('/workflows/templates');
      final body = res.data as Map<String, dynamic>;
      final allTemplates = (body['data'] as List?) ?? [];
      // نعرض جميع القوالب التي يسمح الخادم للمستخدم بإنشائها، بدون تصفية جامدة في الموبايل
      // حتى تعمل الواجهة ديناميكياً مع أي قالب جديد (مثل طلب التعيين / إيقاف الدوام)
      if (!mounted) return;
      setState(() {
        _templates = allTemplates;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error =
            '${t('workflow_create_templates_load_error_prefix')}${e.toString()}';
        _loading = false;
      });
    }
  }

  Future<void> _selectTemplate(int id) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final auth = context.read<AuthProvider>();
    final user = auth.user;
    try {
      final api = context.read<ApiClient>();
      final res = await api.get('/workflows/templates/$id');
      final body = res.data as Map<String, dynamic>;
      final data = body['data'] as Map<String, dynamic>;
      if (!mounted) return;
      setState(() {
        _selectedTemplate = data;
        _employees = data['employees'] ?? [];
        _departments = data['departments'] ?? [];
        _values.clear();
        _files.clear();
        _advanceLimit = data['advance_limit'] as Map<String, dynamic>?;
        _autoFillEmployee =
            user != null &&
            user['employee_id'] != null &&
            (data['code'] == 'hr_leave' ||
                data['code'] == 'hr_missing_attendance');
        // تعبئة بيانات الموظف تلقائياً من حساب المستخدم للإجازات وطلب نسيان البصمة (الطلب للنفس)
        if (_autoFillEmployee) {
          final u = user!;
          _values['employee_id'] = u['employee_id'].toString();
          _values['employee_name'] =
              u['employee_name']?.toString() ?? u['name']?.toString() ?? '';
        }
        _loading = false;
      });
      // جلب الأصناف للطلبات التي تستخدم حقل قائمة الأصناف
      final hasItemsField = ((data['fields'] as List?) ?? []).any(
        (f) => (f as Map?)?['type'] == 'items_list',
      );
      if (hasItemsField) {
        await _loadItems();
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error =
            '${t('workflow_create_template_details_error_prefix')}${e.toString()}';
        _loading = false;
      });
    }
  }

  Future<void> _loadItems() async {
    try {
      final api = context.read<ApiClient>();
      Response res;
      try {
        res = await api.get('/mobile/purchase-request-items');
      } catch (e) {
        // Fallback للـ endpoint القديم إذا لم يكن الجديد منشوراً بعد
        res = await api.get('/mobile/items');
      }
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      setState(() {
        _items = (body['data'] as List? ?? [])
            .map((e) => e as Map<String, dynamic>)
            .toList();
      });
    } catch (e) {
      if (!mounted) return;
      String message = t('workflow_create_items_load_error');
      if (e is DioException && e.response?.data is Map) {
        message = (e.response!.data as Map)['error']?.toString() ?? message;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    }
  }

  Widget _buildItemsListField({
    required String name,
    required String label,
    required bool required,
  }) {
    final items =
        (_values[name] as List?)
            ?.map((e) => Map<String, dynamic>.from(e as Map))
            .toList() ??
        [];
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '$label${required ? ' *' : ''}',
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 6),
          ...items.asMap().entries.map((entry) {
            final i = entry.key;
            final row = entry.value;
            return _buildItemRow(i, row, items, name);
          }),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: () {
                setState(() {
                  items.add({
                    'item_id': '',
                    'item_name': '',
                    'qty': '',
                    'reason': '',
                  });
                  _values[name] = items;
                });
              },
              icon: const Icon(Icons.add),
              label: Text(t('workflow_create_add_item')),
            ),
          ),
          if (required)
            TextFormField(
              decoration: const InputDecoration.collapsed(hintText: ''),
              validator: (_) =>
                  items.isEmpty ||
                      items.any(
                        (r) =>
                            r['item_id'].toString().isEmpty ||
                            (double.tryParse(r['qty'].toString()) ?? 0) <= 0,
                      )
                  ? t('workflow_create_item_required')
                  : null,
            ),
        ],
      ),
    );
  }

  Widget _buildItemRow(
    int index,
    Map<String, dynamic> row,
    List<Map<String, dynamic>> items,
    String fieldName,
  ) {
    final selectedItemId = row['item_id']?.toString() ?? '';
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: _buildSearchableDropdown(
                    name: '${fieldName}_item_$index',
                    label: t('workflow_create_item_name_label'),
                    required: true,
                    options: _items
                        .map<Map<String, dynamic>>(
                          (it) => {
                            'value': it['id'].toString(),
                            'label':
                                '${it['name'] ?? ''} ${it['unit_name'] != null ? '(${it['unit_name']})' : ''}'
                                    .trim(),
                          },
                        )
                        .toList(),
                    initialValue: selectedItemId,
                    onSelected: (v) {
                      setState(() {
                        items[index]['item_id'] = v;
                        final it = _items.firstWhere(
                          (x) => x['id'].toString() == v,
                          orElse: () => <String, dynamic>{},
                        );
                        items[index]['item_name'] =
                            it['name']?.toString() ?? '';
                        items[index]['unit_name'] =
                            it['unit_name']?.toString() ?? '';
                        _values[fieldName] = items;
                        _values['${fieldName}_item_$index'] = v;
                      });
                    },
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.delete, color: Colors.red),
                  onPressed: () {
                    setState(() {
                      items.removeAt(index);
                      _values[fieldName] = items;
                    });
                  },
                ),
              ],
            ),
            const SizedBox(height: 6),
            Row(
              children: [
                Expanded(
                  flex: 2,
                  child: TextFormField(
                    decoration: InputDecoration(
                      labelText: t('workflow_create_qty_label'),
                      border: const OutlineInputBorder(),
                    ),
                    keyboardType: TextInputType.number,
                    initialValue: row['qty']?.toString() ?? '',
                    onChanged: (v) {
                      setState(() {
                        items[index]['qty'] = v;
                        _values[fieldName] = items;
                      });
                    },
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  flex: 3,
                  child: TextFormField(
                    decoration: InputDecoration(
                      labelText: t('workflow_create_reason_label'),
                      border: const OutlineInputBorder(),
                    ),
                    initialValue: row['reason']?.toString() ?? '',
                    onChanged: (v) {
                      setState(() {
                        items[index]['reason'] = v;
                        _values[fieldName] = items;
                      });
                    },
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    _formKey.currentState!.save();
    if (_isLeaveTemplate && _values['_request_category'] == 'daily_leave') {
      final leaveType = _values['leave_type']?.toString();
      final from = DateTime.tryParse(_values['date_from']?.toString() ?? '');
      final to = DateTime.tryParse(_values['date_to']?.toString() ?? '');
      if (leaveType != null && from != null && to != null) {
        final days = to.difference(from).inDays + 1;
        if (leaveType == 'unpaid_short' && days > 3) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(t('workflow_create_short_unpaid_leave_limit')),
            ),
          );
          return;
        }
        if (leaveType == 'unpaid_long' && days < 4) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(t('workflow_create_long_unpaid_leave_limit')),
            ),
          );
          return;
        }
      }
    }
    setState(() => _submitting = true);
    try {
      final api = context.read<ApiClient>();
      final code = _selectedTemplate!['code']?.toString() ?? '';
      final fields = Map<String, dynamic>.from(_values);
      for (final entry in _files.entries) {
        final file = entry.value;
        if (file != null) {
          final bytes = await file.readAsBytes();
          final ext = file.name.contains('.')
              ? file.name.split('.').last.toLowerCase()
              : 'jpg';
          final mime = ext == 'png' ? 'image/png' : 'image/jpeg';
          fields[entry.key] = 'data:$mime;base64,${base64Encode(bytes)}';
        }
      }
      Response res;
      if (code == 'hiring_request') {
        res = await api.post(
          '/hiring-requests',
          data: {
            'title':
                _selectedTemplate!['name']?.toString() ??
                t('workflow_create_default_hiring_title'),
            'job_title': fields['job_title']?.toString() ?? '',
            'department_id': int.tryParse(
              fields['department_id']?.toString() ?? '',
            ),
            'expected_salary':
                double.tryParse(fields['expected_salary']?.toString() ?? '') ??
                0,
            'reason': fields['reason']?.toString() ?? '',
            'notes': fields['notes']?.toString() ?? '',
          },
        );
      } else if (code == 'suspension_request') {
        res = await api.post(
          '/suspension-requests',
          data: {
            'title':
                _selectedTemplate!['name']?.toString() ??
                t('workflow_create_default_suspension_title'),
            'employee_id': int.tryParse(
              fields['employee_id']?.toString() ?? '',
            ),
            'suspension_date': fields['suspension_date']?.toString() ?? '',
            'suspension_type': fields['suspension_type']?.toString() ?? '',
            'reason': fields['reason']?.toString() ?? '',
            'notes': fields['notes']?.toString() ?? '',
          },
        );
      } else {
        res = await api.post(
          '/workflows/requests',
          data: {
            'template_id': _selectedTemplate!['id'],
            'title':
                _selectedTemplate!['name']?.toString() ??
                t('workflow_create_default_approval_title'),
            'fields': fields,
          },
        );
      }
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      if (body['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${t('workflow_create_submitted_prefix')}${body['request_no'] ?? body['id'] ?? ''}',
            ),
          ),
        );
        context.go('/approvals');
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              body['error']?.toString() ?? t('workflow_create_submit_failed'),
            ),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      String message = t('workflow_create_submit_error');
      if (e is DioException && e.response?.data is Map) {
        final data = e.response!.data as Map;
        message =
            data['error']?.toString() ?? data['message']?.toString() ?? message;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Widget _buildField(Map<String, dynamic> field) {
    final name = field['name'] as String;
    final label = (field['label'] as String?) ?? name;
    var type = (field['type'] as String?) ?? 'text';
    final required = field['required'] == true;
    final suffix = required ? ' *' : '';

    // بعض قوالب الخادم تخزن حقل الموظف (employee_id) كنوع number أو text
    // بدلاً من النوع employee؛ لذلك نعامله دائماً كقائمة موظفين قابلة للبحث.
    final isEmployeeField =
        name == 'employee_id' ||
        (name.toLowerCase().startsWith('employee') &&
            name != 'employee_name') ||
        (label.contains('موظف') &&
            (type == 'number' || type == 'text' || type == 'select'));
    if (isEmployeeField && type != 'employee') {
      type = 'employee';
    }

    switch (type) {
      case 'items_list':
        return _buildItemsListField(
          name: name,
          label: label,
          required: required,
        );
      case 'select':
      case 'employee':
        final rawOptions = (field['options'] as List?) ?? [];
        if (type == 'employee') {
          return _buildSearchableDropdown(
            name: name,
            label: label,
            required: required,
            options: _employees.map<Map<String, dynamic>>((e) {
              final emp = e as Map<String, dynamic>;
              return {
                'value': emp['id']?.toString() ?? '',
                'label': (emp['full_name'] ?? '').toString().trim(),
              };
            }).toList(),
            initialValue: _values[name]?.toString(),
            onSelected: (v) => setState(() {
              _values[name] = v;
              if (v != null) {
                final emp =
                    _employees.firstWhere(
                          (e) => e['id']?.toString() == v,
                          orElse: () => <String, dynamic>{},
                        )
                        as Map<String, dynamic>?;
                if (emp != null)
                  _values['${name}_name'] = emp['full_name']?.toString() ?? '';
              }
            }),
          );
        }
        if (name == 'department_id') {
          return _buildSearchableSelect(
            name: name,
            label: label,
            required: required,
            options: _departments.map<Map<String, dynamic>>((d) {
              final dept = d as Map<String, dynamic>;
              return {
                'value': dept['id']?.toString() ?? '',
                'label': dept['name']?.toString() ?? '',
              };
            }).toList(),
            initialValue: _values[name]?.toString(),
            onSelected: (v) => setState(() => _values[name] = v),
          );
        }
        return _buildSearchableSelect(
          name: name,
          label: label,
          required: required,
          options: rawOptions.map<Map<String, dynamic>>((o) {
            if (o is Map) {
              return {
                'value': o['value']?.toString() ?? o.toString(),
                'label':
                    o['label']?.toString() ??
                    o['value']?.toString() ??
                    o.toString(),
              };
            }
            return {'value': o.toString(), 'label': o.toString()};
          }).toList(),
          initialValue: _values[name]?.toString(),
          onSelected: (v) => setState(() => _values[name] = v),
        );
      case 'number':
        return TextFormField(
          decoration: InputDecoration(labelText: '$label$suffix'),
          keyboardType: TextInputType.number,
          initialValue: _values[name]?.toString(),
          onSaved: (v) => _values[name] = v,
          validator: required
              ? (v) => v == null || v.isEmpty
                    ? t('workflow_create_required_field')
                    : null
              : null,
        );
      case 'date':
        return TextFormField(
          decoration: InputDecoration(labelText: '$label$suffix'),
          readOnly: true,
          controller: TextEditingController(
            text: _values[name]?.toString() ?? '',
          ),
          onTap: () async {
            final now = DateTime.now();
            final picked = await showDatePicker(
              context: context,
              initialDate: now,
              firstDate: DateTime(2000),
              lastDate: DateTime(2100),
            );
            if (picked != null) {
              setState(
                () => _values[name] =
                    '${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}',
              );
            }
          },
        );
      case 'datetime':
        return TextFormField(
          decoration: InputDecoration(
            labelText: '$label$suffix',
            suffixIcon: const Icon(Icons.access_time),
          ),
          readOnly: true,
          controller: TextEditingController(
            text: _values[name]?.toString() ?? '',
          ),
          validator: required
              ? (v) => _values[name]?.toString().isEmpty != false
                    ? t('workflow_create_required_field')
                    : null
              : null,
          onTap: () async {
            final now = DateTime.now();
            final pickedDate = await showDatePicker(
              context: context,
              initialDate: now,
              firstDate: DateTime(2000),
              lastDate: DateTime(2100),
            );
            if (pickedDate == null || !mounted) return;
            final pickedTime = await showTimePicker(
              context: context,
              initialTime: TimeOfDay.fromDateTime(now),
              builder: (context, child) => Directionality(
                textDirection: TextDirection.rtl,
                child: child!,
              ),
            );
            if (pickedTime == null || !mounted) return;
            setState(
              () => _values[name] =
                  '${pickedDate.year}-${pickedDate.month.toString().padLeft(2, '0')}-${pickedDate.day.toString().padLeft(2, '0')} ${pickedTime.hour.toString().padLeft(2, '0')}:${pickedTime.minute.toString().padLeft(2, '0')}:00',
            );
          },
        );
      case 'checkbox':
        final checked =
            _values[name] == '1' ||
            _values[name] == true ||
            _values[name] == 'true';
        return CheckboxListTile(
          title: Text(label),
          value: checked,
          onChanged: (v) =>
              setState(() => _values[name] = v == true ? '1' : '0'),
        );
      case 'textarea':
        return TextFormField(
          decoration: InputDecoration(labelText: '$label$suffix'),
          maxLines: 3,
          initialValue: _values[name]?.toString(),
          onSaved: (v) => _values[name] = v,
          validator: required
              ? (v) => v == null || v.isEmpty
                    ? t('workflow_create_required_field')
                    : null
              : null,
        );
      case 'file':
        return ListTile(
          title: Text(label),
          subtitle: Text(
            _files[name]?.name ?? t('workflow_create_no_file_chosen'),
          ),
          trailing: IconButton(
            icon: const Icon(Icons.attach_file),
            onPressed: () async {
              final picker = ImagePicker();
              final file = await picker.pickImage(
                source: ImageSource.gallery,
                imageQuality: 80,
              );
              if (file != null) setState(() => _files[name] = file);
            },
          ),
        );
      default:
        return TextFormField(
          decoration: InputDecoration(labelText: '$label$suffix'),
          initialValue: _values[name]?.toString(),
          onSaved: (v) => _values[name] = v,
          validator: required
              ? (v) => v == null || v.isEmpty
                    ? t('workflow_create_required_field')
                    : null
              : null,
        );
    }
  }

  Widget _buildSearchableSelect({
    required String name,
    required String label,
    required bool required,
    required List<Map<String, dynamic>> options,
    required String? initialValue,
    required void Function(String? value) onSelected,
  }) {
    final suffix = required ? ' *' : '';
    String displayFor(Map<String, dynamic> e) => e['label']?.toString() ?? '';
    final initial = options.firstWhere(
      (o) => o['value']?.toString() == initialValue,
      orElse: () => <String, dynamic>{'value': '', 'label': ''},
    );
    final text = displayFor(initial);

    return Autocomplete<Map<String, dynamic>>(
      initialValue: TextEditingValue(text: text),
      optionsBuilder: (textEditingValue) {
        if (textEditingValue.text.isEmpty) return options;
        final query = textEditingValue.text.toLowerCase();
        return options.where(
          (o) => displayFor(o).toLowerCase().contains(query),
        );
      },
      displayStringForOption: displayFor,
      onSelected: (o) => onSelected(o['value']?.toString()),
      optionsViewBuilder: (context, onSelected, options) {
        return Align(
          alignment: Alignment.topLeft,
          child: Material(
            elevation: 4,
            child: ConstrainedBox(
              constraints: BoxConstraints(
                maxHeight: 250,
                maxWidth: MediaQuery.of(context).size.width - 32,
              ),
              child: ListView.builder(
                padding: EdgeInsets.zero,
                itemCount: options.length,
                itemBuilder: (context, i) {
                  final o = options.elementAt(i);
                  return ListTile(
                    title: Text(
                      displayFor(o),
                      textDirection: TextDirection.rtl,
                      textAlign: TextAlign.right,
                    ),
                    onTap: () => onSelected(o),
                  );
                },
              ),
            ),
          ),
        );
      },
      fieldViewBuilder: (context, textController, focusNode, onFieldSubmitted) {
        return TextFormField(
          controller: textController,
          focusNode: focusNode,
          decoration: InputDecoration(
            labelText: '$label$suffix',
            suffixIcon: const Icon(Icons.search),
          ),
          validator: required
              ? (v) {
                  final selected = _values[name]?.toString();
                  if (selected == null || selected.isEmpty)
                    return t('workflow_create_required_field');
                  return null;
                }
              : null,
        );
      },
    );
  }

  /// قائمة منسدلة للاختيار من بين عدد كبير من الخيارات مع حقل بحث
  Widget _buildSearchableDropdown({
    required String name,
    required String label,
    required bool required,
    required List<Map<String, dynamic>> options,
    required String? initialValue,
    required void Function(String? value) onSelected,
  }) {
    final suffix = required ? ' *' : '';
    String displayFor(Map<String, dynamic> e) => e['label']?.toString() ?? '';
    final selected = options.firstWhere(
      (o) => o['value']?.toString() == initialValue,
      orElse: () => <String, dynamic>{'value': '', 'label': ''},
    );
    final textController = TextEditingController(text: displayFor(selected));

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        controller: textController,
        readOnly: true,
        decoration: InputDecoration(
          labelText: '$label$suffix',
          suffixIcon: const Icon(Icons.arrow_drop_down),
        ),
        validator: required
            ? (v) {
                final selectedValue = _values[name]?.toString();
                if (selectedValue == null || selectedValue.isEmpty)
                  return t('workflow_create_required_field');
                return null;
              }
            : null,
        onTap: () async {
          final queryController = TextEditingController();
          Map<String, dynamic>? picked;
          await showModalBottomSheet<Map<String, dynamic>>(
            context: context,
            isScrollControlled: true,
            builder: (ctx) {
              return StatefulBuilder(
                builder: (ctx, setModalState) {
                  final query = queryController.text.trim().toLowerCase();
                  final filtered = query.isEmpty
                      ? options
                      : options.where((o) {
                          final labelText = displayFor(o).toLowerCase();
                          return labelText.contains(query);
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
                                '${t('workflow_create_choose_prefix')}$label',
                                style: Theme.of(ctx).textTheme.titleMedium
                                    ?.copyWith(fontWeight: FontWeight.bold),
                              ),
                            ),
                            Padding(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 16,
                              ),
                              child: TextField(
                                controller: queryController,
                                textDirection: TextDirection.rtl,
                                decoration: InputDecoration(
                                  prefixIcon: const Icon(Icons.search),
                                  hintText: t('workflow_create_search_by_name'),
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
                              child: ListView.builder(
                                itemCount: filtered.length,
                                itemBuilder: (ctx, i) {
                                  final o = filtered[i];
                                  final labelText = displayFor(o);
                                  final value = o['value']?.toString() ?? '';
                                  final isSelected = initialValue == value;
                                  return ListTile(
                                    title: Text(
                                      labelText,
                                      textDirection: TextDirection.rtl,
                                      textAlign: TextAlign.right,
                                    ),
                                    trailing: isSelected
                                        ? const Icon(
                                            Icons.check,
                                            color: Color(0xFF2E7D32),
                                          )
                                        : null,
                                    onTap: () {
                                      picked = o;
                                      Navigator.pop(ctx, o);
                                    },
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
            final value = picked!['value']?.toString();
            textController.text = displayFor(picked!);
            onSelected(value);
          }
        },
      ),
    );
  }

  Widget _buildSingleDateField(Map<String, dynamic> field) {
    final name = field['name'] as String;
    final label =
        (field['label'] as String?) ?? t('workflow_create_date_label');
    final required = field['required'] == true;
    final suffix = required ? ' *' : '';
    final value = _values[name]?.toString() ?? '';

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        decoration: InputDecoration(
          labelText: '$label$suffix',
          suffixIcon: const Icon(Icons.calendar_today),
        ),
        readOnly: true,
        controller: TextEditingController(
          text: value.isEmpty ? t('workflow_create_choose_date') : value,
        ),
        validator: required
            ? (v) => value.isEmpty ? t('workflow_create_date_required') : null
            : null,
        onTap: () async {
          final picked = await showDatePicker(
            context: context,
            initialDate: DateTime.tryParse(value) ?? DateTime.now(),
            firstDate: DateTime(2000),
            lastDate: DateTime(2100),
          );
          if (picked != null) {
            final formatted =
                '${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
            setState(() {
              _values[name] = formatted;
              _values['date_to'] = formatted;
            });
          }
        },
      ),
    );
  }

  Widget _buildTimeRangeField(
    Map<String, dynamic> startField,
    Map<String, dynamic> endField,
  ) {
    final startName = startField['name'] as String;
    final endName = endField['name'] as String;
    final startLabel =
        (startField['label'] as String?) ??
        t('workflow_create_start_time_label');
    final endLabel =
        (endField['label'] as String?) ?? t('workflow_create_end_time_label');
    final startValue = _values[startName]?.toString() ?? '';
    final endValue = _values[endName]?.toString() ?? '';
    final displayText = (startValue.isNotEmpty && endValue.isNotEmpty)
        ? '$startValue — $endValue'
        : (startValue.isNotEmpty
              ? startValue
              : t('workflow_create_choose_time_range'));

    Future<TimeOfDay?> pickTime(String title, TimeOfDay initial) async {
      return showTimePicker(
        context: context,
        initialTime: initial,
        builder: (context, child) =>
            Directionality(textDirection: TextDirection.rtl, child: child!),
      );
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        decoration: InputDecoration(
          labelText: '$startLabel / $endLabel',
          suffixIcon: const Icon(Icons.access_time),
        ),
        readOnly: true,
        controller: TextEditingController(text: displayText),
        validator: (v) {
          if (startValue.isEmpty)
            return t('workflow_create_start_time_required');
          if (endValue.isEmpty) return t('workflow_create_end_time_required');
          final startParts = startValue.split(':');
          final endParts = endValue.split(':');
          if (startParts.length >= 2 && endParts.length >= 2) {
            final startMinutes =
                int.parse(startParts[0]) * 60 + int.parse(startParts[1]);
            final endMinutes =
                int.parse(endParts[0]) * 60 + int.parse(endParts[1]);
            if (endMinutes <= startMinutes)
              return t('workflow_create_end_after_start');
          }
          return null;
        },
        onTap: () async {
          final now = TimeOfDay.now();
          final initialStart = _parseTimeOfDay(startValue) ?? now;
          final start = await pickTime(
            t('workflow_create_start_time_label'),
            initialStart,
          );
          if (start == null) return;
          final initialEnd = TimeOfDay(
            hour: start.hour + 1,
            minute: start.minute,
          );
          final end = await pickTime(
            t('workflow_create_end_time_label'),
            initialEnd,
          );
          if (end == null) return;
          final startStr =
              '${start.hour.toString().padLeft(2, '0')}:${start.minute.toString().padLeft(2, '0')}';
          final endStr =
              '${end.hour.toString().padLeft(2, '0')}:${end.minute.toString().padLeft(2, '0')}';
          final startMinutes = start.hour * 60 + start.minute;
          final endMinutes = end.hour * 60 + end.minute;
          if (endMinutes <= startMinutes) {
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text(t('workflow_create_end_after_start'))),
              );
            }
            return;
          }
          setState(() {
            _values[startName] = startStr;
            _values[endName] = endStr;
          });
        },
      ),
    );
  }

  TimeOfDay? _parseTimeOfDay(String value) {
    if (value.isEmpty) return null;
    final parts = value.split(':');
    if (parts.length < 2) return null;
    final hour = int.tryParse(parts[0]);
    final minute = int.tryParse(parts[1]);
    if (hour == null || minute == null) return null;
    return TimeOfDay(hour: hour, minute: minute);
  }

  Widget _buildDateRangeField(
    Map<String, dynamic> fromField,
    Map<String, dynamic> toField,
  ) {
    final fromLabel =
        (fromField['label'] as String?) ?? t('workflow_create_from_date_label');
    final toLabel =
        (toField['label'] as String?) ?? t('workflow_create_to_date_label');
    final fromName = fromField['name'] as String;
    final toName = toField['name'] as String;
    final fromRequired = fromField['required'] == true;
    final toRequired = toField['required'] == true;
    final fromValue = _values[fromName]?.toString() ?? '';
    final toValue = _values[toName]?.toString() ?? '';
    final displayText = (fromValue.isNotEmpty && toValue.isNotEmpty)
        ? '$fromValue — $toValue'
        : (fromValue.isNotEmpty
              ? fromValue
              : t('workflow_create_choose_date_range'));

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        decoration: InputDecoration(
          labelText:
              '$fromLabel / $toLabel${(fromRequired || toRequired) ? ' *' : ''}',
          suffixIcon: const Icon(Icons.date_range),
        ),
        readOnly: true,
        controller: TextEditingController(text: displayText),
        validator: (v) {
          if (fromRequired && fromValue.isEmpty)
            return t('workflow_create_start_date_required');
          if (toRequired && toValue.isEmpty)
            return t('workflow_create_end_date_required');
          return null;
        },
        onTap: () async {
          final now = DateTime.now();
          final initialStart = DateTime.tryParse(fromValue) ?? now;
          final initialEnd = DateTime.tryParse(toValue) ?? now;
          final picked = await showDateRangePicker(
            context: context,
            firstDate: DateTime(2000),
            lastDate: DateTime(2100),
            initialDateRange: DateTimeRange(
              start: initialStart,
              end: initialEnd.isBefore(initialStart)
                  ? initialStart
                  : initialEnd,
            ),
            builder: (context, child) =>
                Directionality(textDirection: TextDirection.rtl, child: child!),
          );
          if (picked != null) {
            setState(() {
              _values[fromName] =
                  '${picked.start.year}-${picked.start.month.toString().padLeft(2, '0')}-${picked.start.day.toString().padLeft(2, '0')}';
              _values[toName] =
                  '${picked.end.year}-${picked.end.month.toString().padLeft(2, '0')}-${picked.end.day.toString().padLeft(2, '0')}';
            });
          }
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    context.watch<LocaleProvider>();
    final auth = context.read<AuthProvider>();
    final canAccess =
        auth.can('mobile.approvals') ||
        auth.can('workflow.create') ||
        auth.can('hr.overtime.request') ||
        auth.can('hr.overtime.approve') ||
        auth.can('approvals.decide') ||
        auth.isManagerOrHigher;
    if (!canAccess) {
      return Scaffold(
        appBar: AppBar(title: Text(t('workflow_create_screen_title'))),
        body: Center(child: Text(t('workflow_create_access_denied'))),
      );
    }
    return Scaffold(
      appBar: AppBar(
        title: Text(
          _selectedTemplate == null
              ? t('workflow_create_choose_type_title')
              : '${t('workflow_create_title_prefix')}${_selectedTemplate!['name']?.toString() ?? ''}',
        ),
        leading: _selectedTemplate == null
            ? null
            : IconButton(
                icon: const Icon(Icons.arrow_back),
                onPressed: () {
                  setState(() => _selectedTemplate = null);
                  if (_templates.isEmpty) _loadTemplates();
                },
              ),
      ),
      body: AsyncStateView(
        loading: _loading,
        error: _error,
        onRetry: _selectedTemplate == null
            ? _loadTemplates
            : () => _selectTemplate(_selectedTemplate!['id']),
        child: _selectedTemplate == null ? _buildTemplatesList() : _buildForm(),
      ),
    );
  }

  Widget _buildTemplatesList() {
    final validTemplates = _templates
        .where((x) => x != null && x is Map<String, dynamic>)
        .cast<Map<String, dynamic>>()
        .where((t) => t['code']?.toString() != 'hr_overtime')
        .toList();
    final auth = context.read<AuthProvider>();
    final canRequestOvertime = auth.can('hr.overtime.request');
    final hasOvertimeOptions = canRequestOvertime;
    final isManager = auth.isManagerOrHigher;
    final hasManagerOptions = isManager;
    if (validTemplates.isEmpty && !hasOvertimeOptions && !hasManagerOptions) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Text(
            t('workflow_create_no_options'),
            textAlign: TextAlign.center,
          ),
        ),
      );
    }
    return ListView(
      padding: const EdgeInsets.all(12),
      children: [
        Text(
          t('workflow_create_maintenance_section'),
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
        ),
        const SizedBox(height: 8),
        Card(
          child: ListTile(
            leading: const Icon(
              Icons.handyman_outlined,
              color: Colors.green,
              size: 28,
            ),
            title: Text(
              t('workflow_create_maintenance_item_title'),
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            subtitle: Text(t('workflow_create_maintenance_item_subtitle')),
            onTap: () => context.push('/maintenance/create'),
          ),
        ),
        const SizedBox(height: 16),
        if (hasManagerOptions) ...[
          Text(
            t('workflow_create_manager_section'),
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 8),
          if (isManager) ...[
            Card(
              child: ListTile(
                leading: const Icon(
                  Icons.edit_calendar_outlined,
                  color: Colors.green,
                  size: 28,
                ),
                title: Text(
                  t('workflow_create_shift_assign_title'),
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                subtitle: Text(t('workflow_create_shift_assign_subtitle')),
                onTap: () => context.push('/hr/manager-shift-assign'),
              ),
            ),
          ],
          const SizedBox(height: 16),
        ],
        if (hasOvertimeOptions) ...[
          Text(
            t('workflow_create_overtime_section'),
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 8),
          if (canRequestOvertime)
            Card(
              child: ListTile(
                leading: const Icon(
                  Icons.access_time_outlined,
                  color: Colors.green,
                  size: 28,
                ),
                title: Text(
                  t('workflow_create_overtime_item_title'),
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                subtitle: Text(t('workflow_create_overtime_item_subtitle')),
                onTap: () => context.push('/hr/overtime-request'),
              ),
            ),
          const SizedBox(height: 16),
          Text(
            t('workflow_create_approvals_section'),
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 8),
        ],
        if (validTemplates.isEmpty && !hasOvertimeOptions && !hasManagerOptions)
          Center(child: Text(t('workflow_create_no_templates'))),
        ...validTemplates.map((tpl) {
          final id = tpl['id'];
          return Card(
            child: ListTile(
              leading: _buildTemplateIcon(tpl['icon']?.toString()),
              title: Text(
                tpl['name']?.toString() ??
                    t('workflow_create_unnamed_template'),
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
              subtitle: Text(
                tpl['description']?.toString() ?? '',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              onTap: id != null ? () => _selectTemplate(id as int) : null,
            ),
          );
        }),
      ],
    );
  }

  /// يحول الأيقونات المخزنة في قاعدة البيانات (HTML Font Awesome أو emoji) إلى Widget مناسب
  Widget _buildTemplateIcon(String? rawIcon) {
    if (rawIcon == null || rawIcon.isEmpty) {
      return const Icon(Icons.description, size: 28);
    }
    // إذا كان الأيقونة HTML مثل <i class="fa-solid fa-user-plus"></i>
    final classMatch =
        RegExp(r'<i[^>]*class="([^"]*)"[^>]*>').firstMatch(rawIcon) ??
        RegExp(r"<i[^>]*class='([^']*)'[^>]*>").firstMatch(rawIcon);
    if (classMatch != null) {
      final classes = classMatch.group(1)!.toLowerCase().split(RegExp(r'\s+'));
      final iconData = _faClassToMaterialIcon(classes);
      if (iconData != null) return Icon(iconData, size: 28);
    }
    // إزالة أي وسوم HTML متبقية وعرض النص (emoji أو نص بسيط)
    final text = rawIcon.replaceAll(RegExp(r'<[^>]*>'), '').trim();
    return Text(
      text.isEmpty ? '✓' : text,
      style: const TextStyle(fontSize: 28),
    );
  }

  IconData? _faClassToMaterialIcon(List<String> classes) {
    if (classes.any((c) => c.contains('fa-user-plus'))) return Icons.person_add;
    if (classes.any((c) => c.contains('fa-user-slash')))
      return Icons.person_off;
    if (classes.any((c) => c.contains('fa-user-clock'))) return Icons.timer;
    if (classes.any((c) => c.contains('fa-user'))) return Icons.person;
    if (classes.any((c) => c.contains('fa-check'))) return Icons.check;
    if (classes.any((c) => c.contains('fa-shopping-cart')))
      return Icons.shopping_cart;
    if (classes.any((c) => c.contains('fa-box'))) return Icons.inventory;
    if (classes.any((c) => c.contains('fa-lock'))) return Icons.lock;
    if (classes.any((c) => c.contains('fa-balance-scale')))
      return Icons.balance;
    if (classes.any((c) => c.contains('fa-palm-tree')))
      return Icons.beach_access;
    if (classes.any((c) => c.contains('fa-calendar')))
      return Icons.calendar_today;
    if (classes.any((c) => c.contains('fa-money'))) return Icons.attach_money;
    if (classes.any((c) => c.contains('fa-file'))) return Icons.description;
    if (classes.any((c) => c.contains('fa-plane'))) return Icons.flight;
    if (classes.any((c) => c.contains('fa-stopwatch'))) return Icons.timer;
    if (classes.any((c) => c.contains('fa-hourglass')))
      return Icons.hourglass_empty;
    if (classes.any((c) => c.contains('fa-sign-out'))) return Icons.logout;
    if (classes.any((c) => c.contains('fa-door-open')))
      return Icons.meeting_room;
    if (classes.any((c) => c.contains('fa-clipboard'))) return Icons.assignment;
    if (classes.any((c) => c.contains('fa-list'))) return Icons.list;
    if (classes.any((c) => c.contains('fa-chart-line')))
      return Icons.trending_up;
    if (classes.any((c) => c.contains('fa-wallet')))
      return Icons.account_balance_wallet;
    if (classes.any((c) => c.contains('fa-credit-card')))
      return Icons.credit_card;
    if (classes.any((c) => c.contains('fa-hand-holding')))
      return Icons.monetization_on;
    if (classes.any((c) => c.contains('fa-receipt'))) return Icons.receipt;
    if (classes.any((c) => c.contains('fa-truck'))) return Icons.local_shipping;
    if (classes.any((c) => c.contains('fa-warehouse'))) return Icons.warehouse;
    if (classes.any((c) => c.contains('fa-users'))) return Icons.people;
    if (classes.any((c) => c.contains('fa-briefcase'))) return Icons.work;
    if (classes.any((c) => c.contains('fa-building'))) return Icons.business;
    if (classes.any((c) => c.contains('fa-cog'))) return Icons.settings;
    if (classes.any((c) => c.contains('fa-car'))) return Icons.directions_car;
    if (classes.any((c) => c.contains('fa-hospital')))
      return Icons.local_hospital;
    if (classes.any((c) => c.contains('fa-medkit')))
      return Icons.medical_services;
    if (classes.any((c) => c.contains('fa-graduation-cap')))
      return Icons.school;
    if (classes.any((c) => c.contains('fa-book'))) return Icons.book;
    if (classes.any((c) => c.contains('fa-edit'))) return Icons.edit;
    if (classes.any((c) => c.contains('fa-plus'))) return Icons.add;
    if (classes.any((c) => c.contains('fa-minus'))) return Icons.remove;
    if (classes.any((c) => c.contains('fa-trash'))) return Icons.delete;
    if (classes.any((c) => c.contains('fa-search'))) return Icons.search;
    if (classes.any((c) => c.contains('fa-bell'))) return Icons.notifications;
    if (classes.any((c) => c.contains('fa-comment'))) return Icons.comment;
    if (classes.any((c) => c.contains('fa-star'))) return Icons.star;
    if (classes.any((c) => c.contains('fa-heart'))) return Icons.favorite;
    if (classes.any((c) => c.contains('fa-share'))) return Icons.share;
    if (classes.any((c) => c.contains('fa-flag'))) return Icons.flag;
    if (classes.any((c) => c.contains('fa-bookmark'))) return Icons.bookmark;
    if (classes.any((c) => c.contains('fa-chart-pie'))) return Icons.pie_chart;
    if (classes.any((c) => c.contains('fa-chart-bar'))) return Icons.bar_chart;
    if (classes.any((c) => c.contains('fa-percent'))) return Icons.percent;
    if (classes.any((c) => c.contains('fa-dollar'))) return Icons.attach_money;
    if (classes.any((c) => c.contains('fa-coins'))) return Icons.paid;
    if (classes.any((c) => c.contains('fa-university')))
      return Icons.account_balance;
    if (classes.any((c) => c.contains('fa-handshake'))) return Icons.handshake;
    if (classes.any((c) => c.contains('fa-gavel'))) return Icons.gavel;
    return null;
  }

  Widget _buildForm() {
    if (_isLeaveTemplate) {
      return _buildLeaveForm();
    }
    if (_isMissingAttendanceTemplate) {
      return _buildMissingAttendanceForm();
    }
    return _buildGenericForm();
  }

  Widget _buildAdvanceAmountField(Map<String, dynamic> field) {
    final name = field['name'] as String;
    final label = (field['label'] as String?) ?? name;
    final required = field['required'] == true;
    final suffix = required ? ' *' : '';
    final limit = _advanceLimit;
    final salary = (limit?['salary'] as num?)?.toDouble() ?? 0.0;
    final max = (limit?['max'] as num?)?.toDouble() ?? 0.0;
    final remaining = (limit?['remaining'] as num?)?.toDouble() ?? 0.0;
    final helperText = salary > 0
        ? '${t('hr_advance_salary_label')}$salary${t('hr_advance_max_label')}$max${t('hr_advance_remaining_label')}$remaining'
        : (limit?['error'] as String?) ??
              t('workflow_create_salary_load_error');
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        decoration: InputDecoration(
          labelText: '$label$suffix',
          helperText: helperText,
        ),
        keyboardType: TextInputType.number,
        initialValue: _values[name]?.toString(),
        onChanged: (v) => _values[name] = v,
        onSaved: (v) => _values[name] = v,
        validator: (v) {
          if (required && (v == null || v.isEmpty))
            return t('workflow_create_required_field');
          final val = double.tryParse(v ?? '') ?? 0.0;
          if (val <= 0) return t('hr_advance_amount_invalid');
          if (salary > 0 && val > remaining)
            return remaining <= 0
                ? t('workflow_create_advance_exhausted_month')
                : '${t('hr_advance_amount_exceeds_prefix')}$remaining';
          return null;
        },
      ),
    );
  }

  bool get _isLeaveTemplate {
    return (_selectedTemplate?['code']?.toString() ?? '') == 'hr_leave';
  }

  bool get _isMissingAttendanceTemplate {
    return (_selectedTemplate?['code']?.toString() ?? '') ==
        'hr_missing_attendance';
  }

  Widget _buildGenericForm() {
    final fields = (_selectedTemplate!['fields'] as List?) ?? [];
    final fieldMap = {
      for (final f in fields)
        if (f is Map<String, dynamic>) f['name'] as String: f,
    };
    final children = <Widget>[];
    // employee_name ليس حقل حقيقي في القالب، بل قيمة مساعدة نحفظها من حقل employee_id
    final handled = <String>{'employee_name'};

    if (_selectedTemplate!['code'] == 'hr_advance' &&
        fieldMap.containsKey('amount')) {
      children.add(_buildAdvanceAmountField(fieldMap['amount']!));
      handled.add('amount');
    }

    for (final f in fields) {
      final name = (f as Map<String, dynamic>)['name'] as String;
      if (!handled.contains(name)) {
        children.add(
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: _buildField(f),
          ),
        );
      }
    }

    children.add(const SizedBox(height: 20));
    children.add(
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
              ? t('workflow_create_sending')
              : t('workflow_create_submit_button'),
        ),
      ),
    );

    return Form(
      key: _formKey,
      child: ListView(padding: const EdgeInsets.all(12), children: children),
    );
  }

  void _updateRequestCategory(String category) {
    _values['_request_category'] = category;
    _values['request_type'] = null;
    _values['leave_type'] = null;
    switch (category) {
      case 'daily_leave':
        _values['request_type'] = 'leave';
        break;
      case 'hourly_leave':
        _values['request_type'] = 'leave';
        _values['leave_type'] = 'hourly';
        break;
      case 'work_resumption':
        _values['request_type'] = 'work_resumption';
        _values['leave_type'] = 'n/a';
        break;
    }
    // إزالة القيم غير المستخدمة لتجنب إرسال بيانات زائدة
    if (category != 'daily_leave') {
      _values['date_to'] = null;
    }
    if (category != 'hourly_leave' && category != 'work_resumption') {
      _values['start_time'] = null;
      _values['end_time'] = null;
    }
  }

  Widget _buildRequestCategorySelector() {
    return DropdownButtonFormField<String>(
      initialValue: _values['_request_category']?.toString(),
      decoration: InputDecoration(
        labelText: t('workflow_create_request_type_label'),
      ),
      isExpanded: true,
      isDense: true,
      items: [
        DropdownMenuItem(
          value: 'daily_leave',
          child: Text(t('workflow_create_daily_leave')),
        ),
        DropdownMenuItem(
          value: 'hourly_leave',
          child: Text(t('workflow_create_hourly_leave')),
        ),
        DropdownMenuItem(
          value: 'work_resumption',
          child: Text(t('workflow_create_work_resumption')),
        ),
      ],
      onChanged: (v) {
        if (v != null) setState(() => _updateRequestCategory(v));
      },
      validator: (v) => v == null ? t('workflow_create_required_field') : null,
      onSaved: (v) {},
    );
  }

  Widget _buildDailyLeaveSubtypeSelector() {
    return DropdownButtonFormField<String>(
      initialValue: _values['leave_type']?.toString(),
      decoration: InputDecoration(
        labelText: t('workflow_create_daily_leave_subtype_label'),
      ),
      isExpanded: true,
      isDense: true,
      items: [
        DropdownMenuItem(
          value: 'unpaid_short',
          child: Text(t('workflow_create_unpaid_short')),
        ),
        DropdownMenuItem(
          value: 'unpaid_long',
          child: Text(t('workflow_create_unpaid_long')),
        ),
      ],
      onChanged: (v) => setState(() => _values['leave_type'] = v),
      validator: (v) => v == null ? t('workflow_create_required_field') : null,
      onSaved: (v) {},
    );
  }

  Widget _buildEmployeeSelector() {
    final selectedId = _values['employee_id']?.toString();
    if (_employees.isEmpty) {
      return DropdownButtonFormField<String>(
        decoration: InputDecoration(
          labelText: t('workflow_create_employee_label'),
        ),
        isExpanded: true,
        isDense: true,
        items: const [],
        onChanged: null,
        validator: (v) => t('workflow_create_no_employees_available'),
      );
    }
    return _buildSearchableDropdown(
      name: 'employee_id',
      label: t('workflow_create_the_employee_label'),
      required: true,
      options: _employees.map<Map<String, dynamic>>((e) {
        final emp = e as Map<String, dynamic>;
        return {
          'value': emp['id']?.toString() ?? '',
          'label': (emp['full_name'] ?? '').toString().trim(),
        };
      }).toList(),
      initialValue: selectedId,
      onSelected: (v) {
        if (v != null) {
          final emp =
              _employees.firstWhere(
                    (e) => e['id'].toString() == v,
                    orElse: () => <String, dynamic>{},
                  )
                  as Map<String, dynamic>;
          setState(() {
            _values['employee_id'] = v;
            _values['employee_name'] = emp['full_name']?.toString();
          });
        }
      },
    );
  }

  Widget _buildLeaveForm() {
    final fields = (_selectedTemplate!['fields'] as List?) ?? [];
    final fieldMap = {
      for (final f in fields)
        if (f is Map<String, dynamic>) f['name'] as String: f,
    };
    final category = _values['_request_category']?.toString();
    final isDailyLeave = category == 'daily_leave';
    final isHourlyLeave = category == 'hourly_leave';
    final isWorkResumption = category == 'work_resumption';
    final showTimes = isHourlyLeave || isWorkResumption;

    final children = <Widget>[
      _buildRequestCategorySelector(),
      if (isDailyLeave) _buildDailyLeaveSubtypeSelector(),
      const SizedBox(height: 8),
      if (fieldMap.containsKey('employee_id')) ...[
        if (_values.containsKey('employee_id'))
          ListTile(
            title: Text(t('workflow_create_the_employee_label')),
            subtitle: Text(_values['employee_name']?.toString() ?? ''),
            leading: const Icon(Icons.person),
          )
        else
          _buildEmployeeSelector(),
      ],
      if (isDailyLeave &&
          fieldMap.containsKey('date_from') &&
          fieldMap.containsKey('date_to'))
        _buildDateRangeField(fieldMap['date_from']!, fieldMap['date_to']!)
      else if (showTimes && fieldMap.containsKey('date_from'))
        _buildSingleDateField(fieldMap['date_from']!)
      else ...[
        if (fieldMap.containsKey('date_from'))
          _buildField(fieldMap['date_from']!),
        if (fieldMap.containsKey('date_to')) _buildField(fieldMap['date_to']!),
      ],
      if (showTimes &&
          fieldMap.containsKey('start_time') &&
          fieldMap.containsKey('end_time'))
        _buildTimeRangeField(fieldMap['start_time']!, fieldMap['end_time']!)
      else if (showTimes) ...[
        if (fieldMap.containsKey('start_time'))
          _buildField(fieldMap['start_time']!),
        if (fieldMap.containsKey('end_time'))
          _buildField(fieldMap['end_time']!),
      ],
      if (fieldMap.containsKey('reason')) _buildField(fieldMap['reason']!),
    ];

    const handled = {
      '_request_category',
      'request_type',
      'leave_type',
      'employee_id',
      'employee_name',
      'date_from',
      'date_to',
      'start_time',
      'end_time',
      'reason',
    };
    for (final f in fields) {
      final name = (f as Map<String, dynamic>)['name'] as String;
      if (!handled.contains(name)) {
        children.add(_buildField(f));
      }
    }

    children.add(const SizedBox(height: 20));
    children.add(
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
              ? t('workflow_create_sending')
              : t('workflow_create_submit_button'),
        ),
      ),
    );

    return Form(
      key: _formKey,
      child: ListView(padding: const EdgeInsets.all(12), children: children),
    );
  }

  Widget _buildMissingAttendanceForm() {
    final fields = (_selectedTemplate!['fields'] as List?) ?? [];
    final fieldMap = {
      for (final f in fields)
        if (f is Map<String, dynamic>) f['name'] as String: f,
    };
    final requestTypeField = fieldMap['request_type'];
    final requestTypeLabel =
        (requestTypeField?['label'] as String?) ??
        t('workflow_create_missing_attendance_type_label');
    final reasonField = fieldMap['reason'];
    final reasonLabel =
        (reasonField?['label'] as String?) ??
        t('workflow_create_missing_attendance_reason_label');
    final employeeName = _values['employee_name']?.toString() ?? '';
    final selectedType = _values['request_type']?.toString() ?? 'in';

    return Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.all(12),
        children: [
          if (employeeName.isNotEmpty)
            Card(
              margin: const EdgeInsets.only(bottom: 12),
              child: ListTile(
                leading: const Icon(Icons.person),
                title: Text(t('workflow_create_the_employee_label')),
                subtitle: Text(employeeName),
              ),
            ),
          DropdownButtonFormField<String>(
            initialValue: selectedType,
            decoration: InputDecoration(labelText: '$requestTypeLabel *'),
            isExpanded: true,
            items: [
              DropdownMenuItem(
                value: 'in',
                child: Text(t('workflow_create_clock_in_type')),
              ),
              DropdownMenuItem(
                value: 'out',
                child: Text(t('workflow_create_clock_out_type')),
              ),
            ],
            onChanged: (v) {
              if (v != null) {
                setState(() {
                  _values['request_type'] = v;
                  _values['clock_in_at'] = null;
                  _values['clock_out_at'] = null;
                });
              }
            },
            validator: (v) => v == null || v.isEmpty
                ? t('workflow_create_required_field')
                : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            decoration: InputDecoration(
              labelText: t('workflow_create_datetime_label'),
              suffixIcon: const Icon(Icons.access_time),
            ),
            readOnly: true,
            controller: TextEditingController(
              text:
                  _values['clock_in_at']?.toString() ??
                  _values['clock_out_at']?.toString() ??
                  '',
            ),
            validator: (v) {
              final hasIn =
                  _values['clock_in_at']?.toString().isNotEmpty == true;
              final hasOut =
                  _values['clock_out_at']?.toString().isNotEmpty == true;
              if (!hasIn && !hasOut)
                return t('workflow_create_datetime_required');
              return null;
            },
            onTap: () async {
              final now = DateTime.now();
              final date = await showDatePicker(
                context: context,
                initialDate: now,
                firstDate: DateTime(2000),
                lastDate: DateTime(2100),
              );
              if (date == null || !mounted) return;
              final time = await showTimePicker(
                context: context,
                initialTime: TimeOfDay.fromDateTime(now),
                builder: (context, child) => Directionality(
                  textDirection: TextDirection.rtl,
                  child: child!,
                ),
              );
              if (time == null || !mounted) return;
              final formatted =
                  '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')} ${time.hour.toString().padLeft(2, '0')}:${time.minute.toString().padLeft(2, '0')}:00';
              setState(() {
                if (selectedType == 'in') {
                  _values['clock_in_at'] = formatted;
                  _values['clock_out_at'] = null;
                } else {
                  _values['clock_out_at'] = formatted;
                  _values['clock_in_at'] = null;
                }
              });
            },
          ),
          const SizedBox(height: 12),
          TextFormField(
            decoration: InputDecoration(
              labelText:
                  '$reasonLabel${(reasonField?['required'] == true ? ' *' : '')}',
            ),
            maxLines: 3,
            initialValue: _values['reason']?.toString() ?? '',
            onSaved: (v) => _values['reason'] = v,
            validator: (reasonField?['required'] == true)
                ? (v) => v == null || v.isEmpty
                      ? t('workflow_create_required_field')
                      : null
                : null,
          ),
          const SizedBox(height: 20),
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
                  ? t('workflow_create_sending')
                  : t('workflow_create_submit_button'),
            ),
          ),
        ],
      ),
    );
  }
}
