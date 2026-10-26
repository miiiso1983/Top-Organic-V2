import 'dart:convert';
import 'dart:math';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import '../../core/network/api_client.dart';
import 'salary_detail_screen.dart';

const teamKinds = <String, String>{
  'employees': 'الموظفون',
  'notice': 'التنبيهات',
  'notice_request': 'طلبات التنبيه',
  'warning': 'الإنذارات',
  'transfer': 'نقل الموظفين',
  'photo_loading': 'التحميلات المصورة',
  'all': 'كل السجلات',
};
const archiveTypes = <String, String>{
  'cases': 'التنبيهات والطلبات',
  'warnings': 'الإنذارات السابقة',
  'deductions': 'الخصومات والتسويات',
  'payroll': 'كشوف الرواتب',
  'leaves': 'الإجازات',
  'attendance': 'الحضور والانصراف',
  'advances': 'السلف',
  'evaluations': 'التقييمات',
  'tips': 'التيبس',
  'loadings': 'التحميلات المالية',
};
const teamLabels = <String, String>{
  'id': 'الرقم',
  'warning_type': 'نوع الإنذار',
  'reason': 'السبب',
  'warning_date': 'التاريخ',
  'status': 'الحالة',
  'adjustment_type': 'نوع التسوية',
  'amount': 'المبلغ',
  'period': 'الشهر',
  'base_salary': 'الراتب الأساسي',
  'total_earnings': 'الاستحقاقات',
  'total_deductions': 'الاستقطاعات',
  'net_salary': 'الصافي',
  'leave_type': 'الإجازة',
  'date_from': 'من',
  'date_to': 'إلى',
  'check_type': 'البصمة',
  'checked_at': 'الوقت',
  'advance_type': 'نوع السلفة',
  'remaining_amount': 'المتبقي',
  'total_score': 'الدرجة',
  'evaluation_type': 'نوع التقييم',
  'notes': 'الملاحظات',
  'full_amount': 'التيبس الكامل',
  'prorated_amount': 'التيبس المستحق',
  'subject': 'العنوان',
  'body': 'التفاصيل',
  'kind_label': 'نوع السجل',
  'status_label': 'الحالة',
  'created_at': 'تاريخ الإنشاء',
  'employee_read_at': 'تاريخ القراءة',
  'effective_at': 'سريان النقل',
  'full_name': 'الموظف',
  'emp_code': 'الكود',
};
String teamStatus(Object? v) =>
    {
      'created': 'إنشاء الطلب',
      'resolved': 'معالج',
      'expired': 'منتهي',
      'deduction': 'خصم',
      'penalty': 'جزاء',
      'bonus': 'مكافأة',
      'raise': 'زيادة',
      'short': 'قصيرة الأجل',
      'long': 'طويلة الأجل',
      'monthly': 'شهري',
      'annual': 'سنوية',
      'unpaid': 'بدون راتب',
      'sick': 'مرضية',
      'emergency': 'طارئة',
      'issued': 'صادر',
      'pending_manager': 'بانتظار المدير',
      'pending_hr': 'بانتظار الموارد البشرية',
      'approved': 'معتمد',
      'rejected': 'مرفوض',
      'cancelled': 'ملغى',
      'pending': 'معلق',
      'paid': 'مصروف',
      'draft': 'مسودة',
      'active': 'نشط',
      'written': 'كتابي',
      'verbal': 'شفهي',
      'final': 'نهائي',
      'in': 'دخول',
      'out': 'خروج',
    }[v?.toString()] ??
    v?.toString() ??
    '';
String teamError(Object e) => e is DioException && e.response?.data is Map
    ? (e.response!.data['error'] ?? 'تعذر إكمال العملية').toString()
    : 'تعذر الاتصال؛ أعد المحاولة';
Future<Map<String, dynamic>> teamGet(
  BuildContext c,
  String path, {
  Map<String, dynamic>? query,
}) async => Map<String, dynamic>.from(
  (await c.read<ApiClient>().get('/hr-team$path', query: query)).data['data']
      as Map,
);

class HrTeamScreen extends StatefulWidget {
  const HrTeamScreen({super.key});
  @override
  State<HrTeamScreen> createState() => _HrTeamScreenState();
}

class _HrTeamScreenState extends State<HrTeamScreen> {
  String _tab = 'employees', _status = '', _from = '', _to = '';
  final _search = TextEditingController();
  int _page = 1, _size = 25, _sequence = 0, _optionSequence = 0;
  int? _employeeId;
  List<Map<String, dynamic>> _employeeOptions = [];
  bool _optionsBusy = true;
  String? _optionsError;
  bool _busy = true;
  String? _error;
  Map<String, dynamic> _data = {};
  @override
  void initState() {
    super.initState();
    _loadOptions();
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _loadOptions() async {
    final seq = ++_optionSequence;
    setState(() {
      _optionsBusy = true;
      _optionsError = null;
    });
    try {
      final data = await teamGet(
        context,
        _tab == 'employees' ? '/employees' : '/cases',
        query: {
          'options': '1',
          if (_tab != 'employees' && _tab != 'all') 'kind': _tab,
        },
      );
      if (mounted && seq == _optionSequence) {
        setState(
          () => _employeeOptions = (data['rows'] as List)
              .map((e) => Map<String, dynamic>.from(e as Map))
              .toList(),
        );
      }
    } catch (e) {
      if (mounted && seq == _optionSequence)
        setState(() => _optionsError = teamError(e));
    } finally {
      if (mounted && seq == _optionSequence)
        setState(() => _optionsBusy = false);
    }
  }

  Future<void> _load() async {
    final seq = ++_sequence;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final data = await teamGet(
        context,
        _tab == 'employees' ? '/employees' : '/cases',
        query: {
          'page': _page,
          'page_size': _size,
          if (_employeeId != null) 'employee_id': _employeeId,
          if (_tab != 'employees' && _tab != 'all') 'kind': _tab,
          if (_status.isNotEmpty) 'status': _status,
          if (_from.isNotEmpty) 'from': _from,
          if (_to.isNotEmpty) 'to': _to,
        },
      );
      if (mounted && seq == _sequence) setState(() => _data = data);
    } catch (e) {
      if (mounted && seq == _sequence) setState(() => _error = teamError(e));
    } finally {
      if (mounted && seq == _sequence) setState(() => _busy = false);
    }
  }

  Future<void> _action(Map<String, dynamic> emp, String key) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => key == 'archive' || key == 'payslip'
            ? HrEmployeeArchiveScreen(
                employee: emp,
                initialType: key == 'payslip' ? 'payroll' : 'cases',
              )
            : HrTeamFormScreen(employee: emp, action: key),
      ),
    );
    if (mounted) _load();
  }

  @override
  Widget build(BuildContext context) {
    final rows = (_data['rows'] as List?) ?? [];
    final total = (_data['total'] as num?)?.toInt() ?? 0;
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('الموظفون وسجلي الوظيفي'),
          actions: [
            IconButton(
              onPressed: _busy ? null : _load,
              icon: const Icon(Icons.refresh),
            ),
          ],
        ),
        body: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                children: [
                  DropdownButtonFormField<String>(
                    initialValue: _tab,
                    isExpanded: true,
                    decoration: const InputDecoration(labelText: 'القائمة'),
                    items: teamKinds.entries
                        .map(
                          (e) => DropdownMenuItem(
                            value: e.key,
                            child: Text(e.value),
                          ),
                        )
                        .toList(),
                    onChanged: (v) {
                      setState(() {
                        _tab = v!;
                        _page = 1;
                        _employeeId = null;
                        _search.clear();
                        _employeeOptions = [];
                      });
                      _loadOptions();
                      _load();
                    },
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: DropdownMenu<int>(
                          key: ValueKey('employee-picker-$_tab'),
                          controller: _search,
                          enabled: !_optionsBusy && _optionsError == null,
                          enableFilter: true,
                          enableSearch: true,
                          requestFocusOnTap: true,
                          expandedInsets: EdgeInsets.zero,
                          menuHeight: 280,
                          label: const Text('اسم الموظف أو الكود'),
                          hintText: _optionsBusy
                              ? 'جارٍ تحميل الأسماء…'
                              : 'ابحث واختر موظفاً',
                          dropdownMenuEntries: _employeeOptions.map((e) {
                            final label =
                                '${e['full_name']} (${e['emp_code']})';
                            return DropdownMenuEntry<int>(
                              value: int.parse(e['id'].toString()),
                              label: label,
                              labelWidget: Text(
                                label,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                              ),
                            );
                          }).toList(),
                          onSelected: (id) {
                            setState(() {
                              _employeeId = id;
                              _page = 1;
                            });
                            _load();
                          },
                        ),
                      ),
                      IconButton(
                        tooltip: 'إلغاء اختيار الموظف وعرض الجميع',
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _search.clear();
                          setState(() {
                            _employeeId = null;
                            _page = 1;
                          });
                          FocusScope.of(context).unfocus();
                          _load();
                        },
                      ),
                    ],
                  ),
                  if (_optionsError != null)
                    TextButton.icon(
                      onPressed: _loadOptions,
                      icon: const Icon(Icons.refresh),
                      label: Text(_optionsError!),
                    ),
                  if (_tab != 'employees')
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        DropdownButton<String>(
                          value: _status,
                          items:
                              [
                                    '',
                                    'pending_manager',
                                    'pending_hr',
                                    'issued',
                                    'approved',
                                    'rejected',
                                    'cancelled',
                                  ]
                                  .map(
                                    (v) => DropdownMenuItem(
                                      value: v,
                                      child: Text(
                                        v.isEmpty
                                            ? 'كل الحالات'
                                            : teamStatus(v),
                                      ),
                                    ),
                                  )
                                  .toList(),
                          onChanged: (v) {
                            _status = v!;
                            _page = 1;
                            _load();
                          },
                        ),
                        for (final from in [true, false])
                          TextButton(
                            onPressed: () async {
                              final date = await showDatePicker(
                                context: context,
                                firstDate: DateTime(2000),
                                lastDate: DateTime(2100),
                                initialDate: DateTime.now(),
                              );
                              if (date != null && mounted) {
                                setState(() {
                                  if (from) {
                                    _from = date.toIso8601String().substring(
                                      0,
                                      10,
                                    );
                                  } else {
                                    _to = date.toIso8601String().substring(
                                      0,
                                      10,
                                    );
                                  }
                                  _page = 1;
                                });
                                _load();
                              }
                            },
                            child: Text(
                              '${from ? 'من' : 'إلى'}: ${(from ? _from : _to).isEmpty ? 'كل التواريخ' : (from ? _from : _to)}',
                            ),
                          ),
                        TextButton(
                          onPressed: () {
                            _from = '';
                            _to = '';
                            _page = 1;
                            _load();
                          },
                          child: const Text('مسح التواريخ'),
                        ),
                      ],
                    ),
                ],
              ),
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.all(12),
                child: Text(_error!, style: const TextStyle(color: Colors.red)),
              ),
            Expanded(
              child: _busy
                  ? const Center(child: CircularProgressIndicator())
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView(
                        padding: const EdgeInsets.all(12),
                        children: [
                          if (rows.isEmpty)
                            const Padding(
                              padding: EdgeInsets.all(24),
                              child: Text('لا توجد نتائج'),
                            ),
                          ...rows.map((raw) {
                            final r = Map<String, dynamic>.from(raw as Map);
                            return Card(
                              child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Column(
                                  crossAxisAlignment:
                                      CrossAxisAlignment.stretch,
                                  children: [
                                    Text(
                                      r['full_name']?.toString() ?? '',
                                      style: Theme.of(
                                        context,
                                      ).textTheme.titleMedium,
                                    ),
                                    if (_tab == 'employees') ...[
                                      Text(
                                        [
                                              r['emp_code'],
                                              r['job_title'],
                                              r['department'],
                                            ]
                                            .where(
                                              (v) =>
                                                  v != null &&
                                                  v.toString().isNotEmpty,
                                            )
                                            .join(' • '),
                                      ),
                                      const SizedBox(height: 8),
                                      Wrap(
                                        spacing: 8,
                                        runSpacing: 8,
                                        children:
                                            Map<String, dynamic>.from(
                                                  r['actions'] as Map,
                                                ).entries
                                                .map(
                                                  (a) => OutlinedButton(
                                                    onPressed: () =>
                                                        _action(r, a.key),
                                                    child: Text(
                                                      a.value.toString(),
                                                    ),
                                                  ),
                                                )
                                                .toList(),
                                      ),
                                    ] else ...[
                                      Text(
                                        '${r['kind_label']} — ${r['status_label']}',
                                      ),
                                      Text(r['subject'].toString()),
                                      Text(r['created_at'].toString()),
                                      TextButton(
                                        onPressed: () async {
                                          await Navigator.of(context).push(
                                            MaterialPageRoute(
                                              builder: (_) =>
                                                  HrCaseDetailScreen(
                                                    id: (r['id'] as num)
                                                        .toInt(),
                                                  ),
                                            ),
                                          );
                                          if (mounted) _load();
                                        },
                                        child: const Text('عرض السجل'),
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                            );
                          }),
                        ],
                      ),
                    ),
            ),
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: Wrap(
                  spacing: 8,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    IconButton(
                      onPressed: !_busy && _page > 1
                          ? () {
                              _page--;
                              _load();
                            }
                          : null,
                      icon: const Icon(Icons.chevron_right),
                    ),
                    Text('$_page — $total سجل'),
                    IconButton(
                      onPressed: !_busy && _page * _size < total
                          ? () {
                              _page++;
                              _load();
                            }
                          : null,
                      icon: const Icon(Icons.chevron_left),
                    ),
                    const Text('الصفوف'),
                    DropdownButton<int>(
                      value: _size,
                      items: [10, 25, 50, 100]
                          .map(
                            (n) =>
                                DropdownMenuItem(value: n, child: Text('$n')),
                          )
                          .toList(),
                      onChanged: (n) {
                        _size = n!;
                        _page = 1;
                        _load();
                      },
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class HrTeamFormScreen extends StatefulWidget {
  const HrTeamFormScreen({
    super.key,
    required this.employee,
    required this.action,
  });
  final Map<String, dynamic> employee;
  final String action;
  @override
  State<HrTeamFormScreen> createState() => _HrTeamFormScreenState();
}

class _HrTeamFormScreenState extends State<HrTeamFormScreen> {
  final _form = GlobalKey<FormState>();
  final _controllers = <String, TextEditingController>{};
  final _values = <String, dynamic>{};
  final _images = <String>[];
  late final String _key = List.generate(
    24,
    (_) => Random.secure().nextInt(256).toRadixString(16).padLeft(2, '0'),
  ).join();
  Map<String, dynamic>? _schema;
  String? _error;
  bool _busy = false;
  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final r = await teamGet(
        context,
        '/employees/${widget.employee['id']}/form/${widget.action}',
      );
      if (mounted) setState(() => _schema = r);
    } catch (e) {
      if (mounted) setState(() => _error = teamError(e));
    }
  }

  Future<void> _submit() async {
    if (_busy || !_form.currentState!.validate()) return;
    _form.currentState!.save();
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final data = {
        ..._values,
        'request_key': _key,
        if (_images.isNotEmpty) 'images': _images,
      };
      await context.read<ApiClient>().post(
        '/hr-team/employees/${widget.employee['id']}/action/${widget.action}',
        data: data,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تم حفظ الإجراء وإرساله حسب مسار الموافقات'),
        ),
      );
      Navigator.of(context).pop();
    } catch (e) {
      if (mounted) setState(() => _error = teamError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _field(Map f) {
    final name = f['name'].toString(),
        type = f['type']?.toString() ?? 'text',
        label = f['label'].toString();
    final required = f['required'] == true || f['required'] == 1;
    if (type == 'images')
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(label),
          Wrap(
            spacing: 8,
            children: [
              for (int i = 0; i < _images.length; i++)
                InputChip(
                  label: Text('صورة ${i + 1}'),
                  onDeleted: _busy
                      ? null
                      : () => setState(() => _images.removeAt(i)),
                ),
            ],
          ),
          OutlinedButton.icon(
            onPressed: _busy
                ? null
                : () async {
                    try {
                      final files = await ImagePicker().pickMultiImage(
                        imageQuality: 85,
                      );
                      if (!mounted) return;
                      if (files.length + _images.length > 5)
                        throw Exception('max');
                      final images = <String>[];
                      for (final file in files) {
                        final bytes = await file.readAsBytes();
                        if (bytes.length > 5 * 1024 * 1024)
                          throw Exception('size');
                        images.add(base64Encode(bytes));
                      }
                      if (mounted) setState(() => _images.addAll(images));
                    } catch (_) {
                      if (mounted)
                        setState(
                          () => _error =
                              'اختر حتى خمس صور، بحد أقصى 5MB لكل صورة',
                        );
                    }
                  },
            icon: const Icon(Icons.add_photo_alternate_outlined),
            label: const Text('إرفاق صور'),
          ),
        ],
      );
    if (type == 'select') {
      final opts = f['options'];
      final options = <String, String>{};
      if (opts is Map) {
        for (final e in opts.entries) {
          options[e.key.toString()] = e.value.toString();
        }
      } else if (opts is List) {
        for (final v in opts) {
          if (v is Map) {
            options[v['value'].toString()] = (v['label'] ?? v['value'])
                .toString();
          } else {
            options[v.toString()] = v.toString();
          }
        }
      }
      return DropdownButtonFormField<String>(
        isExpanded: true,
        initialValue: _values[name]?.toString(),
        decoration: InputDecoration(labelText: label),
        items: options.entries
            .map((e) => DropdownMenuItem(value: e.key, child: Text(e.value)))
            .toList(),
        onChanged: _busy ? null : (v) => setState(() => _values[name] = v),
        onSaved: (v) => _values[name] = v,
        validator: (v) =>
            required && (v == null || v.isEmpty) ? 'اختر قيمة' : null,
      );
    }
    final c = _controllers.putIfAbsent(
      name,
      () => TextEditingController(
        text: type == 'month'
            ? DateTime.now().toIso8601String().substring(0, 7)
            : '',
      ),
    );
    return TextFormField(
      controller: c,
      enabled: !_busy,
      decoration: InputDecoration(
        labelText: label,
        hintText: type == 'month' ? 'YYYY-MM' : null,
      ),
      maxLines: type == 'textarea' ? 3 : 1,
      keyboardType: type == 'number'
          ? const TextInputType.numberWithOptions(decimal: true)
          : TextInputType.text,
      readOnly: ['date', 'datetime-local'].contains(type),
      onTap: ['date', 'datetime-local'].contains(type)
          ? () async {
              final d = await showDatePicker(
                context: context,
                initialDate: DateTime.now(),
                firstDate: DateTime(2000),
                lastDate: DateTime(2100),
              );
              if (d == null || !mounted) return;
              String text = d.toIso8601String().substring(0, 10);
              if (type == 'datetime-local') {
                final t = await showTimePicker(
                  context: context,
                  initialTime: TimeOfDay.now(),
                );
                if (t == null || !mounted) return;
                text +=
                    'T${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';
              }
              c.text = text;
            }
          : null,
      validator: (v) =>
          required && (v == null || v.trim().isEmpty) ? 'الحقل مطلوب' : null,
      onSaved: (v) => _values[name] = v ?? '',
    );
  }

  @override
  Widget build(BuildContext context) => Directionality(
    textDirection: TextDirection.rtl,
    child: Scaffold(
      appBar: AppBar(
        title: Text(_schema?['title']?.toString() ?? 'إجراء الموظف'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            widget.employee['full_name'].toString(),
            style: Theme.of(context).textTheme.titleMedium,
          ),
          if (_error != null)
            Text(_error!, style: const TextStyle(color: Colors.red)),
          if (_schema?['advance_snapshot'] is Map) ...[
            Text(
              'صافي الاستحقاق: ${_schema!['advance_snapshot']['net_entitlement'] ?? 0}',
            ),
            Text(
              'المديونية المتبقية: ${_schema!['advance_snapshot']['outstanding_debt'] ?? 0}',
            ),
            Text(
              'الحد المتاح: ${_schema!['advance_snapshot']['remaining'] ?? 0}',
            ),
          ],
          if (_schema == null) ...[
            if (_error == null)
              const Center(child: CircularProgressIndicator())
            else
              TextButton(onPressed: _load, child: const Text('إعادة المحاولة')),
          ] else
            Form(
              key: _form,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (final f in _schema!['fields'] as List)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 9),
                      child: _field(f as Map),
                    ),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: _busy ? null : _submit,
                    child: Text(_busy ? 'جارٍ الإرسال…' : 'إرسال'),
                  ),
                ],
              ),
            ),
        ],
      ),
    ),
  );
}

class HrCaseDetailScreen extends StatefulWidget {
  const HrCaseDetailScreen({super.key, required this.id});
  final int id;
  @override
  State<HrCaseDetailScreen> createState() => _HrCaseDetailScreenState();
}

class _HrCaseDetailScreenState extends State<HrCaseDetailScreen> {
  Map<String, dynamic>? _data;
  String? _error;
  bool _busy = false;
  final _notes = TextEditingController();
  final _images = <String>[];
  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final api = context.read<ApiClient>();
      final r = await teamGet(context, '/cases/${widget.id}');
      final images = <String>[];
      for (final f in r['files'] as List) {
        final res = await api.get('/hr-team/files/${f['id']}/data');
        images.add(res.data['data']['base64'].toString());
      }
      await api.post('/hr-team/cases/${widget.id}/read', data: {});
      if (mounted)
        setState(() {
          _data = r;
          _images
            ..clear()
            ..addAll(images);
          _error = null;
        });
    } catch (e) {
      if (mounted) setState(() => _error = teamError(e));
    }
  }

  Future<void> _decide(String action) async {
    if (_busy) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('تأكيد القرار'),
        content: Text(
          {
            'approve': 'اعتماد / قبول',
            'reject': 'رفض',
            'cancel': 'إلغاء',
          }[action]!,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: const Text('رجوع'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(c, true),
            child: const Text('تأكيد'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    setState(() => _busy = true);
    try {
      await context.read<ApiClient>().post(
        '/hr-team/cases/${widget.id}/decide',
        data: {'action': action, 'notes': _notes.text},
      );
      await _load();
    } catch (e) {
      if (mounted) setState(() => _error = teamError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final r = _data;
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        appBar: AppBar(title: const Text('تفاصيل السجل')),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (_error != null)
              Text(_error!, style: const TextStyle(color: Colors.red)),
            if (r == null) ...[
              if (_error == null)
                const Center(child: CircularProgressIndicator())
              else
                TextButton(
                  onPressed: _load,
                  child: const Text('إعادة المحاولة'),
                ),
            ] else ...[
              Text(
                r['subject'].toString(),
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              Text(r['full_name'].toString()),
              Text(teamStatus(r['status'])),
              const SizedBox(height: 16),
              Text(r['body'].toString()),
              Text('أنشأه: ${r['creator_name']}'),
              if (r['new_manager_name'] != null)
                Text('المدير الجديد: ${r['new_manager_name']}'),
              Text('قراءة الموظف: ${r['employee_read_at'] ?? 'لم يقرأ بعد'}'),
              for (final image in _images)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Image.memory(
                    base64Decode(image),
                    height: 240,
                    fit: BoxFit.contain,
                  ),
                ),
              const Divider(),
              for (final event in r['events'] as List)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Text(
                    '${event['created_at']} — ${event['actor_name']}\n${teamStatus(event['action'])} ${event['notes'] ?? ''}',
                  ),
                ),
              if ((r['actions'] as List).isNotEmpty) ...[
                TextField(
                  controller: _notes,
                  maxLines: 3,
                  decoration: const InputDecoration(
                    labelText: 'ملاحظات القرار (مطلوبة للرفض والإلغاء)',
                  ),
                ),
                Wrap(
                  spacing: 8,
                  children: [
                    for (final a in r['actions'] as List)
                      FilledButton(
                        onPressed: _busy ? null : () => _decide(a.toString()),
                        child: Text(
                          {
                            'approve': 'اعتماد / قبول',
                            'reject': 'رفض',
                            'cancel': 'إلغاء',
                          }[a]!,
                        ),
                      ),
                  ],
                ),
              ],
            ],
          ],
        ),
      ),
    );
  }
}

class HrEmployeeArchiveScreen extends StatefulWidget {
  const HrEmployeeArchiveScreen({
    super.key,
    required this.employee,
    this.initialType = 'cases',
  });
  final Map<String, dynamic> employee;
  final String initialType;
  @override
  State<HrEmployeeArchiveScreen> createState() =>
      _HrEmployeeArchiveScreenState();
}

class _HrEmployeeArchiveScreenState extends State<HrEmployeeArchiveScreen> {
  late String _type = widget.initialType;
  int _page = 1, _sequence = 0;
  Map<String, dynamic>? _data;
  String? _error;
  bool _busy = true;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final seq = ++_sequence;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final r = await teamGet(
        context,
        _type == 'cases'
            ? '/cases'
            : '/employees/${widget.employee['id']}/archive',
        query: {
          'employee_id': widget.employee['id'],
          'type': _type,
          'page': _page,
          'page_size': 25,
        },
      );
      if (mounted && seq == _sequence) setState(() => _data = r);
    } catch (e) {
      if (mounted && seq == _sequence) setState(() => _error = teamError(e));
    } finally {
      if (mounted && seq == _sequence) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final financial = (widget.employee['actions'] as Map).containsKey(
      'payslip',
    );
    final choices = archiveTypes.entries.where(
      (e) =>
          financial ||
          ![
            'deductions',
            'payroll',
            'advances',
            'tips',
            'loadings',
          ].contains(e.key),
    );
    final rows = (_data?['rows'] as List?) ?? [];
    final total = (_data?['total'] as num?)?.toInt() ?? 0;
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        appBar: AppBar(title: Text('أرشيف ${widget.employee['full_name']}')),
        body: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(12),
              child: DropdownButtonFormField<String>(
                isExpanded: true,
                initialValue: _type,
                items: choices
                    .map(
                      (e) =>
                          DropdownMenuItem(value: e.key, child: Text(e.value)),
                    )
                    .toList(),
                onChanged: (v) {
                  _type = v!;
                  _page = 1;
                  _load();
                },
              ),
            ),
            if (_error != null)
              Text(_error!, style: const TextStyle(color: Colors.red)),
            Expanded(
              child: _busy
                  ? const Center(child: CircularProgressIndicator())
                  : ListView(
                      padding: const EdgeInsets.all(12),
                      children: [
                        if (rows.isEmpty) const Text('لا توجد سجلات'),
                        for (final r in rows)
                          Card(
                            child: Padding(
                              padding: const EdgeInsets.all(14),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  for (final entry in (r as Map).entries)
                                    if (entry.value != null &&
                                        ![
                                          'kind',
                                          'employee_id',
                                        ].contains(entry.key))
                                      Text(
                                        '${teamLabels[entry.key] ?? entry.key}: ${teamStatus(entry.value)}',
                                      ),
                                  if (_type == 'payroll')
                                    TextButton(
                                      onPressed: () =>
                                          Navigator.of(context).push(
                                            MaterialPageRoute(
                                              builder: (_) =>
                                                  SalaryDetailScreen(
                                                    payrollId: (r['id'] as num)
                                                        .toInt(),
                                                    period: r['period']
                                                        .toString(),
                                                    employeeId:
                                                        (widget.employee['id']
                                                                as num)
                                                            .toInt(),
                                                  ),
                                            ),
                                          ),
                                      child: const Text('كشف الراتب التفصيلي'),
                                    ),
                                  if (_type == 'cases')
                                    TextButton(
                                      onPressed: () =>
                                          Navigator.of(context).push(
                                            MaterialPageRoute(
                                              builder: (_) =>
                                                  HrCaseDetailScreen(
                                                    id: (r['id'] as num)
                                                        .toInt(),
                                                  ),
                                            ),
                                          ),
                                      child: const Text('عرض السجل'),
                                    ),
                                ],
                              ),
                            ),
                          ),
                      ],
                    ),
            ),
            SafeArea(
              top: false,
              child: Wrap(
                spacing: 12,
                children: [
                  TextButton(
                    onPressed: !_busy && _page > 1
                        ? () {
                            _page--;
                            _load();
                          }
                        : null,
                    child: const Text('السابق'),
                  ),
                  Text('$_page — $total سجل'),
                  TextButton(
                    onPressed: !_busy && _page * 25 < total
                        ? () {
                            _page++;
                            _load();
                          }
                        : null,
                    child: const Text('التالي'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
