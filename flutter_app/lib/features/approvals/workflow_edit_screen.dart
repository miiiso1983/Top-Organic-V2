import 'advance_policy.dart';
import 'advance_snapshot_card.dart';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';

/// شاشة تعديل طلب موافقة (Workflow) بعد طلب المدير المباشر
class WorkflowEditScreen extends StatefulWidget {
  const WorkflowEditScreen({super.key, required this.requestId});
  final int requestId;

  @override
  State<WorkflowEditScreen> createState() => _WorkflowEditScreenState();
}

class _WorkflowEditScreenState extends State<WorkflowEditScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _request;
  Map<String, dynamic>? _advanceLimit;
  final Map<String, dynamic> _values = {};
  final Map<String, XFile?> _files = {};
  final TextEditingController _notesController = TextEditingController();
  bool _submitting = false;
  final _formKey = GlobalKey<FormState>();

  /// ترجمة نص حسب اللغة الحالية، قابلة للاستخدام خارج build() أيضاً
  String t(String key) =>
      AppStrings.t(key, context.read<LocaleProvider>().locale.languageCode);

  String _statusLabel(String status) {
    return {
          'draft': t('workflow_edit_status_draft'),
          'pending': t('workflow_edit_status_pending'),
          'approved': t('workflow_edit_status_approved'),
          'rejected': t('workflow_edit_status_rejected'),
          'returned_for_edit': t('workflow_edit_status_returned_for_edit'),
          'cancelled': t('workflow_edit_status_cancelled'),
        }[status] ??
        status;
  }

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
      final res = await api.get('/workflows/requests/${widget.requestId}');
      final body = res.data as Map<String, dynamic>;
      if (body['ok'] != true) {
        setState(() {
          _error = body['error']?.toString() ?? t('workflow_edit_load_failed');
          _loading = false;
        });
        return;
      }
      final req = body['data']['request'] as Map<String, dynamic>;
      _request = req;
      _advanceLimit = req['advance_limit'];
      _values.addAll(req['data'] as Map<String, dynamic>? ?? {});
      if (!_values.containsKey('_request_category') &&
          _values['request_type'] != null) {
        _inferCategory();
      }
      setState(() => _loading = false);
    } catch (e) {
      String message = t('workflow_edit_load_error');
      if (e is DioException && e.response?.data is Map) {
        message = (e.response!.data as Map)['error']?.toString() ?? message;
      }
      setState(() {
        _error = message;
        _loading = false;
      });
    }
  }

  void _inferCategory() {
    final type = _values['request_type']?.toString();
    final leaveType = _values['leave_type']?.toString();
    if (type == 'leave') {
      _values['_request_category'] = leaveType == 'hourly'
          ? 'hourly_leave'
          : 'daily_leave';
    } else if (type == 'exit') {
      _values['_request_category'] = 'exit';
    } else if (type == 'delay') {
      _values['_request_category'] = 'delay';
    } else if (type == 'work_resumption') {
      _values['_request_category'] = 'work_resumption';
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    _formKey.currentState!.save();
    setState(() => _submitting = true);
    try {
      final api = context.read<ApiClient>();
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
      final status = _request?['status']?.toString() ?? '';
      final isResubmit = status == 'returned_for_edit' || status == 'draft';
      final endpoint = isResubmit
          ? '/workflows/requests/${widget.requestId}/resubmit'
          : '/workflows/requests/${widget.requestId}/edit';
      final successMessage = isResubmit
          ? t('workflow_edit_resubmitted_message')
          : t('workflow_edit_saved_message');
      final payload = {
        'data': fields,
        'title': _request?['title']?.toString() ?? '',
        'notes': _notesController.text,
      };
      final res = await api.post(endpoint, data: payload);
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      if (body['ok'] == true) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(successMessage)));
        context.go('/approvals');
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              body['error']?.toString() ?? t('workflow_edit_save_failed'),
            ),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      String message = t('workflow_edit_save_error');
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

  Widget _buildField(Map<String, dynamic> field) {
    final name = field['name'] as String;
    final label = (field['label'] as String?) ?? name;
    final type = (field['type'] as String?) ?? 'text';
    final required = field['required'] == true;
    final suffix = required ? ' *' : '';

    switch (type) {
      case 'select':
        final options =
            (field['options'] as List?)?.map((e) => e.toString()).toList() ??
            [];
        final current = _values.containsKey(name)
            ? _values[name]?.toString()
            : null;
        return DropdownButtonFormField<String>(
          key: ValueKey('select-$name-$current'),
          initialValue: current,
          decoration: InputDecoration(labelText: '$label$suffix'),
          items: options
              .map((o) => DropdownMenuItem(value: o, child: Text(o)))
              .toList(),
          onChanged: (v) => setState(() => _values[name] = v),
          validator: required
              ? (v) => v == null || v.isEmpty
                    ? t('workflow_edit_amount_required')
                    : null
              : null,
          onSaved: (v) => _values[name] = v,
        );
      case 'number':
        return TextFormField(
          decoration: InputDecoration(labelText: '$label$suffix'),
          keyboardType: TextInputType.number,
          initialValue: _values[name]?.toString(),
          onSaved: (v) => _values[name] = v,
          validator: required
              ? (v) => v == null || v.isEmpty
                    ? t('workflow_edit_amount_required')
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
            final picked = await showDatePicker(
              context: context,
              initialDate: DateTime.now(),
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
                    ? t('workflow_edit_amount_required')
                    : null
              : null,
        );
      case 'file':
        return ListTile(
          title: Text(label),
          subtitle: Text(
            _files[name]?.name ??
                (_values[name] != null
                    ? t('workflow_edit_saved_file_label')
                    : t('workflow_edit_no_file_chosen')),
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
                    ? t('workflow_edit_amount_required')
                    : null
              : null,
        );
    }
  }

  Widget _buildStatusChip(String status) {
    final colors = {
      'draft': Colors.grey,
      'pending': Colors.orange,
      'approved': Colors.green,
      'rejected': Colors.red,
      'returned_for_edit': Colors.deepPurple,
      'cancelled': Colors.black54,
    };
    return Chip(
      label: Text(
        _statusLabel(status),
        style: const TextStyle(color: Colors.white, fontSize: 12),
      ),
      backgroundColor: colors[status] ?? Colors.blueGrey,
    );
  }

  Widget _buildReturnNotice() {
    final history = (_request?['history'] as List?) ?? [];
    final returnEntry = history.cast<Map<String, dynamic>>().firstWhere(
      (h) => h['action']?.toString() == 'returned_for_edit',
      orElse: () => {},
    );
    if (returnEntry.isEmpty) return const SizedBox.shrink();
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.deepPurple[50],
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            t('workflow_edit_manager_note_title'),
            style: const TextStyle(
              fontWeight: FontWeight.bold,
              color: Colors.deepPurple,
            ),
          ),
          Text(
            returnEntry['notes']?.toString() ??
                t('workflow_edit_manager_note_default'),
            style: const TextStyle(color: Colors.black87),
          ),
        ],
      ),
    );
  }

  Widget _buildForm() {
    final fields = (_request?['fields'] as List?) ?? [];
    final fieldMap = {
      for (final f in fields)
        if (f is Map<String, dynamic>) f['name'] as String: f,
    };
    final children = <Widget>[
      _buildReturnNotice(),
      if (_advanceLimit != null &&
          AdvancePolicy.isAdvance(_request?['template_code']))
        AdvanceSnapshotCard(
          snapshot: _advanceLimit!,
          code: _request!['template_code'],
        ),
      if (fieldMap.containsKey('amount') &&
          AdvancePolicy.isAdvance(_request?['template_code']))
        _buildAdvanceAmountField(fieldMap['amount']!),
      for (final f in fields)
        if (!{
          'employee_id',
          'employee_name',
          if (AdvancePolicy.isAdvance(_request?['template_code'])) 'amount',
        }.contains((f as Map<String, dynamic>)['name']))
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: _buildField(f),
          ),
      Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: TextFormField(
          controller: _notesController,
          decoration: InputDecoration(
            labelText: t('workflow_edit_notes_label'),
            hintText: t('workflow_edit_notes_hint'),
          ),
          maxLines: 3,
        ),
      ),
      const SizedBox(height: 20),
      Builder(
        builder: (context) {
          final status = _request?['status']?.toString() ?? '';
          final buttonLabel = status == 'returned_for_edit' || status == 'draft'
              ? t('workflow_edit_resave_button')
              : t('workflow_edit_save_button');
          return ElevatedButton.icon(
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
            label: Text(_submitting ? t('workflow_edit_saving') : buttonLabel),
          );
        },
      ),
    ];
    return Form(
      key: _formKey,
      child: ListView(padding: const EdgeInsets.all(12), children: children),
    );
  }

  Widget _buildAdvanceAmountField(Map<String, dynamic> field) {
    final name = field['name'] as String;
    final label = (field['label'] as String?) ?? name;
    final required = field['required'] == true;
    final suffix = required ? ' *' : '';
    final limit = _advanceLimit;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        decoration: InputDecoration(labelText: '$label$suffix'),
        keyboardType: TextInputType.number,
        initialValue: _values[name]?.toString(),
        onChanged: (v) => _values[name] = v,
        onSaved: (v) => _values[name] = v,
        validator: (v) => AdvancePolicy.validateAmount(
          v,
          _request!['template_code'],
          limit,
          english: context.read<LocaleProvider>().locale.languageCode == 'en',
        ),
      ),
    );
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    context.watch<LocaleProvider>();
    final status = _request?['status']?.toString() ?? '';
    final canEdit = _request?['can_edit'] == true;
    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            Expanded(
              child: Text(
                '${t('workflow_edit_title_prefix')}${_request?['template_name']?.toString() ?? t('workflow_edit_title_fallback')}',
              ),
            ),
            if (status.isNotEmpty) _buildStatusChip(status),
          ],
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.go('/approvals'),
        ),
      ),
      body: AsyncStateView(
        loading: _loading,
        error: _error,
        onRetry: _load,
        child: _request == null
            ? Center(child: Text(t('workflow_edit_no_data')))
            : canEdit
            ? _buildForm()
            : Center(
                child: Text(
                  '${t('workflow_edit_cannot_edit_prefix')}${_statusLabel(status)}',
                ),
              ),
      ),
    );
  }
}
