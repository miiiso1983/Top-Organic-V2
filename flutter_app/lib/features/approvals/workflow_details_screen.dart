import 'advance_snapshot_card.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';

/// شاشة تفاصيل طلب الموافقة (Workflow Request)
/// تعرض بيانات الطلب وحقوله وسجل الإجراءات، مع حقل ملاحظات
/// وأزرار: موافقة / رفض / إعادة للتعديل (لمن يملك صلاحية القرار).
class WorkflowDetailsScreen extends StatefulWidget {
  final int requestId;
  const WorkflowDetailsScreen({super.key, required this.requestId});

  @override
  State<WorkflowDetailsScreen> createState() => _WorkflowDetailsScreenState();
}

class _WorkflowDetailsScreenState extends State<WorkflowDetailsScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _request;
  List<dynamic> _history = [];
  bool _canDecide = false;
  bool _submitting = false;
  final _notesController = TextEditingController();

  /// ترجمة نص حسب اللغة الحالية، قابلة للاستخدام خارج build() أيضاً
  String t(String key) =>
      AppStrings.t(key, context.read<LocaleProvider>().locale.languageCode);

  String _statusLabel(String status) {
    return {
          'draft': t('workflow_details_status_draft'),
          'pending': t('workflow_details_status_pending'),
          'approved': t('workflow_details_status_approved'),
          'rejected': t('workflow_details_status_rejected'),
          'returned_for_edit': t('workflow_details_status_returned_for_edit'),
          'cancelled': t('workflow_details_status_cancelled'),
        }[status] ??
        status;
  }

  static const _statusColors = {
    'draft': Colors.grey,
    'pending': Colors.orange,
    'approved': Colors.green,
    'rejected': Colors.red,
    'returned_for_edit': Colors.deepPurple,
    'cancelled': Colors.black54,
  };

  String _actionLabel(String action) {
    return {
          'submitted': t('workflow_details_action_submitted'),
          'approved': t('workflow_details_action_approved'),
          'rejected': t('workflow_details_action_rejected'),
          'returned_for_edit': t('workflow_details_action_returned_for_edit'),
          'resubmitted': t('workflow_details_action_resubmitted'),
          'cancelled': t('workflow_details_action_cancelled'),
          'edited': t('workflow_details_action_edited'),
          'commented': t('workflow_details_action_commented'),
          'delegated': t('workflow_details_action_delegated'),
        }[action] ??
        action;
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
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
      final data = body['data'] as Map<String, dynamic>;
      if (!mounted) return;
      setState(() {
        _request = data['request'] as Map<String, dynamic>;
        _history = (data['history'] as List?) ?? [];
        _canDecide = data['can_decide'] == true;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      String message = t('workflow_details_load_error');
      if (e is DioException && e.response?.data is Map) {
        message = (e.response!.data as Map)['error']?.toString() ?? message;
      }
      setState(() {
        _error = message;
        _loading = false;
      });
    }
  }

  Future<void> _decide(String decision) async {
    final notes = _notesController.text.trim();
    if ((decision == 'rejected' || decision == 'returned_for_edit') &&
        notes.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            decision == 'rejected'
                ? t('workflow_details_rejection_reason_required')
                : t('workflow_details_edit_instructions_required'),
          ),
        ),
      );
      return;
    }
    setState(() => _submitting = true);
    try {
      final api = context.read<ApiClient>();
      final res = await api.post(
        '/workflows/requests/${widget.requestId}/decide',
        data: {'decision': decision, 'notes': notes},
      );
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      if (body['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_decisionSuccessMessage(decision))),
        );
        Navigator.pop(context, true);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              body['error']?.toString() ??
                  t('workflow_details_decision_failed'),
            ),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      String message = t('workflow_details_decision_error');
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

  String _decisionSuccessMessage(String decision) {
    switch (decision) {
      case 'approved':
        return t('workflow_details_approved_message');
      case 'rejected':
        return t('workflow_details_rejected_message');
      case 'returned_for_edit':
        return t('workflow_details_returned_message');
      default:
        return t('workflow_details_decision_recorded');
    }
  }

  @override
  Widget build(BuildContext context) {
    context.watch<LocaleProvider>();
    final title =
        _request?['template_name']?.toString() ??
        _request?['title']?.toString() ??
        t('workflow_details_title_fallback');
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: AsyncStateView(
        loading: _loading,
        error: _error,
        onRetry: _load,
        child: _request == null
            ? const SizedBox.shrink()
            : Column(
                children: [
                  Expanded(
                    child: RefreshIndicator(
                      onRefresh: _load,
                      child: ListView(
                        padding: const EdgeInsets.all(12),
                        children: [
                          _buildHeaderCard(),
                          const SizedBox(height: 12),
                          if (_request?['advance_limit'] is Map)
                            AdvanceSnapshotCard(
                              snapshot: Map<String, dynamic>.from(
                                _request!['advance_limit'],
                              ),
                              code: _request!['template_code'],
                            ),
                          if (_request?['advance_payment'] is Map)
                            ListTile(
                              leading: const Icon(Icons.payments_outlined),
                              title: Text(
                                _request!['advance_payment']['paid_at'] != null
                                    ? (context.read<LocaleProvider>().isArabic
                                          ? 'تم تسليم السلفة'
                                          : 'Advance paid')
                                    : _request!['advance_payment']['accountant_approved_at'] !=
                                          null
                                    ? (context.read<LocaleProvider>().isArabic
                                          ? 'معتمدة للصرف — بانتظار التسليم'
                                          : 'Authorized — awaiting payment')
                                    : (context.read<LocaleProvider>().isArabic
                                          ? 'لم يتم الصرف'
                                          : 'Not paid'),
                              ),
                              subtitle:
                                  _request!['advance_payment']['paid_at'] !=
                                      null
                                  ? Text(
                                      '${_request!['advance_payment']['paid_at']}',
                                    )
                                  : null,
                            ),
                          _buildFieldsCard(),
                          const SizedBox(height: 12),
                          _buildHistoryCard(),
                        ],
                      ),
                    ),
                  ),
                  if (_canDecide) ...[_buildNotesField(), _buildActions()],
                ],
              ),
      ),
    );
  }

  Widget _buildHeaderCard() {
    final r = _request!;
    final status = r['status']?.toString() ?? 'pending';
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    r['title']?.toString() ??
                        r['template_name']?.toString() ??
                        '',
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                    ),
                  ),
                ),
                Chip(
                  label: Text(
                    _statusLabel(status),
                    style: const TextStyle(color: Colors.white, fontSize: 12),
                  ),
                  backgroundColor: _statusColors[status] ?? Colors.blueGrey,
                ),
              ],
            ),
            const Divider(),
            _infoRow(
              t('workflow_details_request_no_label'),
              r['request_no']?.toString() ?? '#${r['id']}',
            ),
            _infoRow(
              t('workflow_details_requester_label'),
              r['requester_name']?.toString() ?? '',
            ),
            if (r['requester_job_title']?.toString().isNotEmpty == true)
              _infoRow(
                t('workflow_details_job_title_label'),
                r['requester_job_title'].toString(),
              ),
            if (r['requester_department']?.toString().isNotEmpty == true)
              _infoRow(
                t('workflow_details_department_label'),
                r['requester_department'].toString(),
              ),
            if (r['requester_position']?.toString().isNotEmpty == true)
              _infoRow(
                t('workflow_details_position_label'),
                r['requester_position'].toString(),
              ),
            if (r['employee_name']?.toString().isNotEmpty == true)
              _infoRow(
                t('workflow_details_employee_label'),
                r['employee_name'].toString(),
              ),
            if ((r['amount'] is num && (r['amount'] as num) > 0) ||
                (double.tryParse(r['amount']?.toString() ?? '') ?? 0) > 0)
              _infoRow(
                t('workflow_details_amount_label'),
                r['amount'].toString(),
              ),
            if (r['created_at']?.toString().isNotEmpty == true)
              _infoRow(
                t('workflow_details_created_at_label'),
                r['created_at'].toString(),
              ),
          ],
        ),
      ),
    );
  }

  Widget _infoRow(String label, String value) {
    if (value.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: TextStyle(color: Colors.grey[700], fontSize: 13),
            ),
          ),
          Expanded(child: Text(value, style: const TextStyle(fontSize: 14))),
        ],
      ),
    );
  }

  /// عرض حقول الطلب المخزنة في data وفق تعريف fields في القالب
  Widget _buildFieldsCard() {
    final r = _request!;
    final data = (r['data'] as Map?)?.cast<String, dynamic>() ?? {};
    final fields = (r['fields'] as List?) ?? [];
    final rows = <Widget>[];
    final shownKeys = <String>{};
    for (final f in fields) {
      if (f is! Map) continue;
      final name = f['name']?.toString() ?? '';
      final label = f['label']?.toString() ?? name;
      final type = f['type']?.toString() ?? 'text';
      shownKeys.add(name);
      if (type == 'items_list') {
        final items = (data[name] as List?) ?? [];
        if (items.isNotEmpty) rows.add(_buildItemsTable(label, items));
        continue;
      }
      final value = data[name];
      if (value == null || value.toString().isEmpty) continue;
      if (type == 'file') {
        rows.add(_buildAttachmentRow(label, value.toString()));
      } else {
        rows.add(_infoRow(label, value.toString()));
      }
    }
    // أي قيم إضافية في data غير معرفة في القالب (مثل employee_name)
    for (final entry in data.entries) {
      if (shownKeys.contains(entry.key)) continue;
      if (entry.key.startsWith('_') || entry.key == 'advance_snapshot')
        continue;
      final v = entry.value;
      if (v == null || v.toString().isEmpty) continue;
      rows.add(_infoRow(entry.key, v.toString()));
    }
    // بنود طلب الشراء المُثريّة من الخادم
    if (r['purchase_request_items'] is List &&
        (r['purchase_request_items'] as List).isNotEmpty) {
      rows.add(_buildPurchaseItemsTable((r['purchase_request_items'] as List)));
    }
    if (rows.isEmpty) return const SizedBox.shrink();
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              t('workflow_details_data_section_title'),
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
            ),
            const Divider(),
            ...rows,
          ],
        ),
      ),
    );
  }

  Widget _buildItemsTable(String label, List<dynamic> items) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
        ),
        const SizedBox(height: 6),
        Table(
          columnWidths: const {
            0: FlexColumnWidth(3),
            1: FlexColumnWidth(1),
            2: FlexColumnWidth(2),
          },
          border: TableBorder.all(color: Colors.grey.shade300, width: 1),
          children: [
            TableRow(
              decoration: BoxDecoration(color: Colors.grey.shade100),
              children: [
                Padding(
                  padding: const EdgeInsets.all(6),
                  child: Text(
                    t('workflow_details_items_column_item'),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(6),
                  child: Text(
                    t('workflow_details_items_column_qty'),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(6),
                  child: Text(
                    t('workflow_details_items_column_reason'),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
            ...items.map((it) {
              final m = it as Map;
              final itemName =
                  m['item_name']?.toString() ?? m['name']?.toString() ?? '';
              final qty =
                  m['qty']?.toString() ?? m['quantity']?.toString() ?? '';
              final reason = m['reason']?.toString() ?? '';
              return TableRow(
                children: [
                  Padding(
                    padding: const EdgeInsets.all(6),
                    child: Text(itemName),
                  ),
                  Padding(padding: const EdgeInsets.all(6), child: Text(qty)),
                  Padding(
                    padding: const EdgeInsets.all(6),
                    child: Text(reason),
                  ),
                ],
              );
            }),
          ],
        ),
        const SizedBox(height: 8),
      ],
    );
  }

  Widget _buildPurchaseItemsTable(List<dynamic> items) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          t('workflow_details_purchase_items_title'),
          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
        ),
        const SizedBox(height: 6),
        Table(
          columnWidths: const {
            0: FlexColumnWidth(3),
            1: FlexColumnWidth(2),
            2: FlexColumnWidth(2),
          },
          border: TableBorder.all(color: Colors.grey.shade300, width: 1),
          children: [
            TableRow(
              decoration: BoxDecoration(color: Colors.grey.shade100),
              children: [
                Padding(
                  padding: const EdgeInsets.all(6),
                  child: Text(
                    t('workflow_details_items_column_item'),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(6),
                  child: Text(
                    t('workflow_details_items_column_qty'),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(6),
                  child: Text(
                    t('workflow_details_items_column_reason'),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
            ...items.map((it) {
              final m = it as Map;
              return TableRow(
                children: [
                  Padding(
                    padding: const EdgeInsets.all(6),
                    child: Text(
                      m['item_name']?.toString() ??
                          m['new_item_name']?.toString() ??
                          '',
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.all(6),
                    child: Text(m['qty']?.toString() ?? ''),
                  ),
                  Padding(
                    padding: const EdgeInsets.all(6),
                    child: Text(m['notes']?.toString() ?? ''),
                  ),
                ],
              );
            }),
          ],
        ),
        const SizedBox(height: 8),
      ],
    );
  }

  Widget _buildAttachmentRow(String label, String value) {
    final isPath = value.isNotEmpty && !value.startsWith('data:');
    return _infoRow(
      label,
      isPath
          ? t('workflow_details_attachment_uploaded')
          : t('workflow_details_attachment_generic'),
    );
  }

  Widget _buildHistoryCard() {
    if (_history.isEmpty) return const SizedBox.shrink();
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              t('workflow_details_history_title'),
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
            ),
            const Divider(),
            ..._history.map((h) {
              final m = h as Map;
              final action = m['action']?.toString() ?? '';
              final actor =
                  m['actor_real_name']?.toString() ??
                  m['actor_name']?.toString() ??
                  '';
              final notes = m['notes']?.toString() ?? '';
              final stepTitle = m['step_title']?.toString() ?? '';
              final at = m['created_at']?.toString() ?? '';
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${_actionLabel(action)}${actor.isNotEmpty ? ' — $actor' : ''}${stepTitle.isNotEmpty ? ' ($stepTitle)' : ''}',
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (notes.isNotEmpty)
                      Text(
                        notes,
                        style: TextStyle(fontSize: 13, color: Colors.grey[800]),
                      ),
                    if (at.isNotEmpty)
                      Text(
                        at,
                        style: TextStyle(fontSize: 11, color: Colors.grey[600]),
                      ),
                  ],
                ),
              );
            }),
          ],
        ),
      ),
    );
  }

  Widget _buildNotesField() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      child: TextField(
        controller: _notesController,
        enabled: !_submitting,
        maxLines: 2,
        decoration: InputDecoration(
          labelText: t('workflow_details_notes_label'),
          border: const OutlineInputBorder(),
        ),
      ),
    );
  }

  Widget _buildActions() {
    final canReturn =
        _request?['can_return'] == true ||
        (_request?['step'] is Map &&
            (_request!['step'] as Map)['can_return_for_edit'] == true);
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: _submitting ? null : () => _decide('approved'),
                    icon: _submitting
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.check_circle),
                    label: Text(t('workflow_details_approve_button')),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.green,
                      foregroundColor: Colors.white,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: _submitting ? null : () => _decide('rejected'),
                    icon: const Icon(Icons.cancel),
                    label: Text(t('workflow_details_reject_button')),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.red,
                      foregroundColor: Colors.white,
                    ),
                  ),
                ),
              ],
            ),
            if (canReturn) ...[
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: _submitting
                    ? null
                    : () => _decide('returned_for_edit'),
                icon: const Icon(Icons.edit_note, color: Colors.deepPurple),
                label: Text(
                  t('workflow_details_return_for_edit_button'),
                  style: const TextStyle(color: Colors.deepPurple),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
