import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:dio/dio.dart';
import '../../core/data/cached_repository.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../features/auth/auth_provider.dart';
import '../../shared/widgets/offline_banner.dart';
import 'workflow_details_screen.dart';

class ApprovalsScreen extends StatefulWidget {
  const ApprovalsScreen({super.key});

  @override
  State<ApprovalsScreen> createState() => _ApprovalsScreenState();
}

/// عنصر موحّد لعرضه في جدول الطلبات/الموافقات المدمج
class _ApprovalEntry {
  const _ApprovalEntry({
    required this.data,
    required this.isPending,
    this.isMaintenance = false,
  });
  final Map data;
  final bool isPending;
  final bool isMaintenance;
}

class _ApprovalsScreenState extends State<ApprovalsScreen> {
  bool _loading = true;
  String? _error;
  String? _offlineNotice;
  List<dynamic> _myRequests = [];
  List<dynamic> _pending = [];
  List<dynamic> _templates = [];
  List<dynamic> _maintenance = [];

  String _statusLabel(String status, String Function(String) t) {
    return {
          'draft': t('approvals_status_draft'),
          'pending': t('approvals_status_pending'),
          'approved': t('approvals_status_approved'),
          'rejected': t('approvals_status_rejected'),
          'returned_for_edit': t('approvals_status_returned_for_edit'),
          'cancelled': t('approvals_status_cancelled'),
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

  String _maintStatusLabel(String status, String Function(String) t) {
    return {
          'pending': t('approvals_maint_status_pending'),
          'assigned': t('approvals_maint_status_assigned'),
          'in_progress': t('approvals_maint_status_in_progress'),
          'completed': t('approvals_maint_status_completed'),
          'confirmed': t('approvals_maint_status_confirmed'),
          'cancelled': t('approvals_maint_status_cancelled'),
        }[status] ??
        status;
  }

  static const _maintStatusColors = {
    'pending': Colors.orange,
    'assigned': Colors.blue,
    'in_progress': Colors.deepPurple,
    'completed': Colors.green,
    'confirmed': Colors.teal,
    'cancelled': Colors.red,
  };

  @override
  void initState() {
    super.initState();
    _load();
  }

  /// تحويل آمن للقيم القادمة من الخادم (قد تصل كنص أو رقم) إلى عدد صحيح
  int _toInt(dynamic v) {
    if (v is int) return v;
    if (v is num) return v.toInt();
    return int.tryParse(v?.toString() ?? '') ?? 0;
  }

  /// دمج "طلباتي" (الجاهزة/غير المؤرشفة) مع "المعلقة لدي" بأولوية ظهور المعلقة أولاً،
  /// إضافة إلى طلبات الصيانة (التي لها نظام حالة مستقل عن محرك الموافقات).
  /// الطلبات المعتمدة أو المرفوضة (أو المؤكدة/الملغاة لطلبات الصيانة) تنتقل إلى صفحة الأرشيف ولا تظهر هنا.
  List<_ApprovalEntry> get _mergedList {
    final pendingKeys = <String>{};
    final entries = <_ApprovalEntry>[];
    for (final p in _pending) {
      final m = p as Map;
      entries.add(_ApprovalEntry(data: m, isPending: true));
      pendingKeys.add('w${_toInt(m['id'])}');
    }
    for (final m in _maintenance) {
      final row = m as Map;
      if (row['needs_action'] != true) continue;
      entries.add(
        _ApprovalEntry(data: row, isPending: true, isMaintenance: true),
      );
      pendingKeys.add('m${_toInt(row['id'])}');
    }
    for (final r in _myRequests) {
      final m = r as Map;
      final id = _toInt(m['id']);
      if (pendingKeys.contains('w$id')) continue;
      final status = m['status']?.toString() ?? '';
      if (status == 'approved' || status == 'rejected') continue;
      entries.add(_ApprovalEntry(data: m, isPending: false));
    }
    for (final m in _maintenance) {
      final row = m as Map;
      if (row['is_requester'] != true) continue;
      final id = _toInt(row['id']);
      if (pendingKeys.contains('m$id')) continue;
      final status = row['status']?.toString() ?? '';
      if (status == 'confirmed' || status == 'cancelled') continue;
      entries.add(
        _ApprovalEntry(data: row, isPending: false, isMaintenance: true),
      );
    }
    return entries;
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
      _offlineNotice = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final myRes = await repo.getJson('/workflows/my-requests', 'my_requests');
      final myBody = myRes.data as Map<String, dynamic>;
      final pendingRes = await repo.getJson(
        '/workflows/pending',
        'pending_approvals',
      );
      final pendingBody = pendingRes.data as Map<String, dynamic>;
      List<dynamic> templates = [];
      List<dynamic> maintenance = [];
      if (!mounted) return;
      try {
        final api = context.read<ApiClient>();
        final templatesRes = await api.get('/workflows/templates');
        final templatesBody = templatesRes.data as Map<String, dynamic>;
        templates = (templatesBody['data'] as List?) ?? [];
      } catch (_) {
        // القائمة المنسدلة لأنواع الطلبات اختيارية، لا نعطّل الشاشة الرئيسية بسببها
      }
      if (!mounted) return;
      try {
        final maintRes = await repo.getJson('/maintenance', 'maintenance');
        final maintBody = maintRes.data as Map<String, dynamic>;
        maintenance = (maintBody['data'] as List?) ?? [];
      } catch (_) {
        // تعذر جلب طلبات الصيانة لا يجب أن يعطّل الشاشة الرئيسية
      }
      if (!mounted) return;
      setState(() {
        _myRequests = (myBody['data'] as List?) ?? [];
        _pending = (pendingBody['data'] as List?) ?? [];
        _templates = templates;
        _maintenance = maintenance;
        _offlineNotice = myRes.fromCache
            ? myRes.errorMessage
            : (pendingRes.fromCache ? pendingRes.errorMessage : null);
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      String message = AppStrings.t('approvals_load_error', lang);
      if (e is DioException && e.response?.data is Map) {
        message = (e.response!.data as Map)['error']?.toString() ?? message;
      } else if (e is DioException) {
        message =
            '${AppStrings.t('approvals_connection_error_prefix', lang)}${e.type})';
      }
      setState(() => _error = message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _decide(int id, String decision) async {
    final api = context.read<ApiClient>();
    try {
      await api.post(
        '/workflows/requests/$id/decide',
        data: {'decision': decision},
      );
      _load();
    } catch (_) {
      if (mounted) {
        final lang = context.read<LocaleProvider>().locale.languageCode;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(AppStrings.t('approvals_decide_error', lang))),
        );
      }
    }
  }

  Future<void> _openDetails(int id) async {
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => WorkflowDetailsScreen(requestId: id)),
    );
    if (changed == true) _load();
  }

  Future<void> _openMaintenanceDetails(int id) async {
    await context.push('/maintenance/$id');
    _load();
  }

  Widget _buildMaintStatusChip(String status, String Function(String) t) {
    final label = _maintStatusLabel(status, t);
    final color = _maintStatusColors[status] ?? Colors.blueGrey;
    return Chip(
      label: Text(
        label,
        style: const TextStyle(color: Colors.white, fontSize: 12),
      ),
      backgroundColor: color,
      padding: EdgeInsets.zero,
      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
    );
  }

  Widget _buildMaintenanceCard(
    _ApprovalEntry entry,
    String Function(String) t,
  ) {
    final r = entry.data;
    final id = _toInt(r['id']);
    final status = r['status']?.toString() ?? 'pending';
    return Card(
      color: entry.isPending ? Colors.orange[50] : null,
      child: ListTile(
        leading: _buildMaintStatusChip(status, t),
        title: Text(
          r['title']?.toString() ?? t('approvals_maintenance_fallback_title'),
        ),
        subtitle: Text(
          '${t('approvals_requester_label')}${r['requester_name']?.toString() ?? '-'}'
          '${r['assigned_name'] != null ? '${t('approvals_assigned_label')}${r['assigned_name']}' : ''}',
        ),
        onTap: () => _openMaintenanceDetails(id),
      ),
    );
  }

  Future<void> _cancel(int id) async {
    final api = context.read<ApiClient>();
    try {
      await api.post('/workflows/requests/$id/cancel');
      _load();
    } catch (_) {
      if (mounted) {
        final lang = context.read<LocaleProvider>().locale.languageCode;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(AppStrings.t('approvals_cancel_error', lang))),
        );
      }
    }
  }

  Widget _buildStatusChip(String status, String Function(String) t) {
    final label = _statusLabel(status, t);
    final color = _statusColors[status] ?? Colors.blueGrey;
    return Chip(
      label: Text(
        label,
        style: const TextStyle(color: Colors.white, fontSize: 12),
      ),
      backgroundColor: color,
      padding: EdgeInsets.zero,
      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
    );
  }

  Widget _buildMergedList(String Function(String) t) {
    final entries = _mergedList;
    if (entries.isEmpty) {
      return ListView(
        children: [
          Padding(
            padding: const EdgeInsets.all(32),
            child: Center(child: Text(t('approvals_empty'))),
          ),
        ],
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.all(12),
      itemCount: entries.length,
      itemBuilder: (context, i) => _buildEntryCard(entries[i], t),
    );
  }

  Widget _buildEntryCard(_ApprovalEntry entry, String Function(String) t) {
    if (entry.isMaintenance) return _buildMaintenanceCard(entry, t);

    final r = entry.data;
    final id = _toInt(r['id']);

    if (entry.isPending) {
      final stepTitle =
          r['step']?['title']?.toString() ?? r['step_title']?.toString() ?? '';
      return Card(
        color: Colors.orange[50],
        child: ListTile(
          leading: _buildStatusChip('pending', t),
          title: Text(
            r['template_name']?.toString() ??
                r['title']?.toString() ??
                t('approvals_request_fallback_title'),
          ),
          subtitle: Text(
            '${t('approvals_from_label')}${r['requester_name']?.toString() ?? r['requester']?.toString() ?? ''}${stepTitle.isNotEmpty ? '${t('approvals_step_label')}$stepTitle' : ''}',
          ),
          trailing: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              IconButton(
                icon: const Icon(Icons.check_circle, color: Colors.green),
                onPressed: () => _decide(id, 'approved'),
              ),
              IconButton(
                icon: const Icon(Icons.cancel, color: Colors.red),
                onPressed: () => _decide(id, 'rejected'),
              ),
              if (r['can_return'] == true ||
                  r['step']?['can_return_for_edit'] == true)
                IconButton(
                  icon: const Icon(Icons.edit_note, color: Colors.deepPurple),
                  onPressed: () => _decide(id, 'returned_for_edit'),
                ),
            ],
          ),
          onTap: () => _openDetails(id),
        ),
      );
    }

    final status = r['status']?.toString() ?? 'pending';
    final canEdit =
        status == 'returned_for_edit' ||
        status == 'draft' ||
        status == 'pending';
    final canCancel =
        status == 'draft' ||
        status == 'pending' ||
        status == 'returned_for_edit';
    return Card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ListTile(
            leading: _buildStatusChip(status, t),
            title: Text(
              r['template_name']?.toString() ??
                  r['title']?.toString() ??
                  t('approvals_request_fallback_title'),
            ),
            subtitle: Text(
              '${t('approvals_requester_label')}${r['requester_name']?.toString() ?? r['requester']?.toString() ?? '-'}${t('approvals_amount_label')}${r['amount'] ?? 0}',
            ),
            onTap: () => _openDetails(id),
          ),
          if (r['return_notes']?.toString().isNotEmpty == true)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: Text(
                '${t('approvals_manager_note_label')}${r['return_notes']}',
                style: TextStyle(color: Colors.deepPurple[700], fontSize: 13),
              ),
            ),
          OverflowBar(
            alignment: MainAxisAlignment.end,
            children: [
              if (canEdit)
                TextButton.icon(
                  icon: const Icon(Icons.edit, color: Colors.deepPurple),
                  label: Text(
                    t('approvals_edit_button'),
                    style: const TextStyle(color: Colors.deepPurple),
                  ),
                  onPressed: () => context.go('/approvals/edit/$id'),
                ),
              if (canCancel)
                TextButton.icon(
                  icon: const Icon(Icons.cancel, color: Colors.red),
                  label: Text(
                    t('approvals_cancel_button'),
                    style: const TextStyle(color: Colors.red),
                  ),
                  onPressed: () async {
                    final confirmed = await showDialog<bool>(
                      context: context,
                      builder: (context) => AlertDialog(
                        title: Text(t('approvals_cancel_dialog_title')),
                        content: Text(t('approvals_cancel_dialog_content')),
                        actions: [
                          TextButton(
                            onPressed: () => Navigator.pop(context, false),
                            child: Text(t('approvals_dialog_back')),
                          ),
                          TextButton(
                            onPressed: () => Navigator.pop(context, true),
                            child: Text(
                              t('approvals_cancel_button'),
                              style: const TextStyle(color: Colors.red),
                            ),
                          ),
                        ],
                      ),
                    );
                    if (confirmed == true) _cancel(id);
                  },
                ),
            ],
          ),
        ],
      ),
    );
  }

  /// عناصر القائمة المنسدلة بأنواع الطلبات المتاحة للموظف الحالي
  List<PopupMenuEntry<String>> _requestTypeMenuItems(
    AuthProvider auth,
    String Function(String) t,
  ) {
    final canRequestOvertime = auth.can('hr.overtime.request');
    final isManager = auth.isManagerOrHigher;
    final validTemplates = _templates
        .where((x) => x != null && x is Map<String, dynamic>)
        .cast<Map<String, dynamic>>()
        .where((tpl) => tpl['code']?.toString() != 'hr_overtime')
        .toList();

    return [
      PopupMenuItem(
        value: 'maintenance',
        child: ListTile(
          leading: const Icon(Icons.handyman_outlined),
          title: Text(t('approvals_maintenance_fallback_title')),
        ),
      ),
      if (isManager) ...[
        PopupMenuItem(
          value: 'shift_assign',
          child: ListTile(
            leading: const Icon(Icons.edit_calendar_outlined),
            title: Text(t('approvals_menu_shift_assign')),
          ),
        ),
      ],
      if (canRequestOvertime)
        PopupMenuItem(
          value: 'overtime_request',
          child: ListTile(
            leading: const Icon(Icons.access_time_outlined),
            title: Text(t('approvals_menu_overtime')),
          ),
        ),
      for (final tpl in validTemplates)
        PopupMenuItem(
          value: 'template:${tpl['id']}',
          child: ListTile(
            leading: const Icon(Icons.description_outlined),
            title: Text(
              tpl['name']?.toString() ?? t('approvals_template_fallback'),
            ),
          ),
        ),
    ];
  }

  void _handleRequestTypeSelected(String value) {
    switch (value) {
      case 'maintenance':
        context.push('/maintenance/create');
        break;
      case 'shift_assign':
        context.push('/hr/manager-shift-assign');
        break;
      case 'overtime_request':
        context.push('/hr/overtime-request');
        break;
      default:
        if (value.startsWith('template:')) {
          final id = int.tryParse(value.substring('template:'.length));
          context.push('/approvals/create', extra: id);
        }
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final auth = context.watch<AuthProvider>();
    // متاح لكل موظف: طلب الصيانة متاح للجميع بلا شرط صلاحية
    final canCreate = true;
    return Scaffold(
      appBar: AppBar(
        title: Text(t('approvals_title')),
        actions: [
          if (canCreate) ...[
            IconButton(
              icon: const Icon(Icons.add_circle, size: 28),
              tooltip: t('approvals_create_tooltip'),
              onPressed: () => context.push('/approvals/create'),
            ),
            PopupMenuButton<String>(
              tooltip: t('approvals_choose_type_tooltip'),
              icon: const Icon(Icons.arrow_drop_down_circle_outlined),
              itemBuilder: (context) => _requestTypeMenuItems(auth, t),
              onSelected: _handleRequestTypeSelected,
            ),
          ],
        ],
      ),
      body: Column(
        children: [
          OfflineBanner(message: _offlineNotice),
          Expanded(
            child: AsyncStateView(
              loading: _loading,
              error: _error,
              onRetry: _load,
              child: RefreshIndicator(
                onRefresh: _load,
                child: _buildMergedList(t),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
