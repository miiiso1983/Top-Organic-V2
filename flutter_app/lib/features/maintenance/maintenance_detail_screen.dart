import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/config.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../core/network/api_client.dart';
import '../../shared/widgets/offline_banner.dart';

class MaintenanceDetailScreen extends StatefulWidget {
  const MaintenanceDetailScreen({super.key, required this.id});
  final int id;

  @override
  State<MaintenanceDetailScreen> createState() =>
      _MaintenanceDetailScreenState();
}

class _MaintenanceDetailScreenState extends State<MaintenanceDetailScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _row;

  /// ترجمة نص حسب اللغة الحالية، قابلة للاستخدام خارج build() أيضاً
  String t(String key) =>
      AppStrings.t(key, context.read<LocaleProvider>().locale.languageCode);

  Map<String, String> _statusLabels() => {
    'pending': t('maintenance_detail_status_pending'),
    'assigned': t('maintenance_detail_status_assigned'),
    'in_progress': t('maintenance_detail_status_in_progress'),
    'completed': t('maintenance_detail_status_completed'),
    'confirmed': t('maintenance_detail_status_confirmed'),
    'cancelled': t('maintenance_detail_status_cancelled'),
  };

  static const _statusColors = {
    'pending': Colors.orange,
    'assigned': Colors.blue,
    'in_progress': Colors.deepPurple,
    'completed': Colors.green,
    'confirmed': Colors.teal,
    'cancelled': Colors.red,
  };

  Map<String, String> _priorityLabels() => {
    'low': t('maintenance_detail_priority_low'),
    'normal': t('maintenance_detail_priority_normal'),
    'high': t('maintenance_detail_priority_high'),
    'urgent': t('maintenance_detail_priority_urgent'),
  };

  Map<String, String> _workStatusLabels() => {
    'parts_requested': t('maintenance_detail_work_status_parts_requested'),
    'in_maintenance': t('maintenance_detail_work_status_in_maintenance'),
    'external_maintenance': t(
      'maintenance_detail_work_status_external_maintenance',
    ),
    'completed': t('maintenance_detail_work_status_completed'),
  };

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
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson(
        '/maintenance/${widget.id}',
        'maintenance_${widget.id}',
      );
      if (!mounted) return;
      final data = res.data as Map<String, dynamic>;
      setState(() => _row = data['data'] as Map<String, dynamic>?);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = t('maintenance_detail_load_error'));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _transition(String action) async {
    final repo = context.read<CachedRepository>();
    final res = await repo.postOrQueue(
      '/maintenance/${widget.id}/$action',
      {},
      entity: 'maintenance',
    );
    if (mounted && res.errorMessage != null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(res.errorMessage!)));
    }
    _load();
  }

  /// نافذة تحديث حالة الصيانة عند إنهاء المهمة: تحتوي قائمة منسدلة بالحالة،
  /// حقل نصي للملاحظات والأدوات المستخدمة، وحقل رقمي اختياري لتكلفة الصيانة
  Future<void> _updateWorkStatus() async {
    String workStatus = 'in_maintenance';
    final notesController = TextEditingController();
    final costController = TextEditingController();
    final result = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(t('maintenance_detail_update_status_title')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                DropdownButtonFormField<String>(
                  initialValue: workStatus,
                  decoration: InputDecoration(
                    labelText: t('maintenance_detail_status_label'),
                  ),
                  items: _workStatusLabels().entries
                      .map(
                        (e) => DropdownMenuItem(
                          value: e.key,
                          child: Text(e.value),
                        ),
                      )
                      .toList(),
                  onChanged: (v) =>
                      setDialogState(() => workStatus = v ?? 'in_maintenance'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: notesController,
                  decoration: InputDecoration(
                    labelText: t('maintenance_detail_notes_tools_label'),
                  ),
                  maxLines: 3,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: costController,
                  decoration: InputDecoration(
                    labelText: t('maintenance_detail_cost_label'),
                  ),
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: Text(t('maintenance_detail_cancel_button')),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(context, true),
              child: Text(t('maintenance_detail_save_button')),
            ),
          ],
        ),
      ),
    );
    if (result != true || !mounted) return;
    final repo = context.read<CachedRepository>();
    final res = await repo.postOrQueue('/maintenance/${widget.id}/finish', {
      'work_status': workStatus,
      'resolution_notes': notesController.text.trim(),
      if (costController.text.trim().isNotEmpty)
        'cost': costController.text.trim(),
    }, entity: 'maintenance');
    if (mounted && res.errorMessage != null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(res.errorMessage!)));
    }
    _load();
  }

  Future<void> _assign() async {
    final employees = await _fetchMaintenanceEmployees();
    if (!mounted) return;
    if (employees.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(t('maintenance_detail_no_maintenance_employees')),
        ),
      );
      return;
    }
    final searchController = TextEditingController();
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            final query = searchController.text.trim().toLowerCase();
            final filtered = query.isEmpty
                ? employees
                : employees
                      .where(
                        (e) => (e['full_name']?.toString() ?? '')
                            .toLowerCase()
                            .contains(query),
                      )
                      .toList();
            return SafeArea(
              child: Padding(
                padding: EdgeInsets.only(
                  bottom: MediaQuery.of(context).viewInsets.bottom,
                ),
                child: SizedBox(
                  height: MediaQuery.of(context).size.height * 0.6,
                  child: Column(
                    children: [
                      Padding(
                        padding: const EdgeInsets.all(12),
                        child: TextField(
                          controller: searchController,
                          decoration: InputDecoration(
                            prefixIcon: const Icon(Icons.search),
                            hintText: t('maintenance_detail_search_by_name'),
                            border: const OutlineInputBorder(),
                          ),
                          onChanged: (_) => setModalState(() {}),
                        ),
                      ),
                      Expanded(
                        child: filtered.isEmpty
                            ? Center(
                                child: Text(t('maintenance_detail_no_results')),
                              )
                            : ListView.builder(
                                shrinkWrap: true,
                                itemCount: filtered.length,
                                itemBuilder: (context, i) {
                                  final e = filtered[i];
                                  return ListTile(
                                    title: Text(
                                      e['full_name']?.toString() ?? '—',
                                    ),
                                    onTap: () async {
                                      final scaffold = ScaffoldMessenger.of(
                                        context,
                                      );
                                      final repo = context
                                          .read<CachedRepository>();
                                      Navigator.pop(context);
                                      final res = await repo.postOrQueue(
                                        '/maintenance/${widget.id}/assign',
                                        {'assigned_to': e['id']},
                                        entity: 'maintenance',
                                      );
                                      if (mounted && res.errorMessage != null) {
                                        scaffold.showSnackBar(
                                          SnackBar(
                                            content: Text(res.errorMessage!),
                                          ),
                                        );
                                      }
                                      _load();
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
  }

  Future<List<dynamic>> _fetchMaintenanceEmployees() async {
    try {
      final api = context.read<ApiClient>();
      final res = await api.get('/maintenance/employees');
      final body = res.data as Map<String, dynamic>;
      return (body['data'] as List?) ?? [];
    } catch (_) {
      return [];
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(t('maintenance_detail_title'))),
      body: RefreshIndicator(
        onRefresh: _load,
        child: AsyncStateView(
          loading: _loading,
          error: _error,
          onRetry: _load,
          child: _row == null
              ? const SizedBox.shrink()
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _buildInfo(
                        t('maintenance_detail_number_label'),
                        _row!['request_number']?.toString() ?? '—',
                      ),
                      _buildInfo(
                        t('maintenance_detail_fault_label'),
                        _row!['title']?.toString() ?? '—',
                      ),
                      _buildInfo(
                        t('maintenance_detail_device_label'),
                        _row!['device_name']?.toString() ?? '—',
                      ),
                      _buildInfo(
                        t('maintenance_detail_status_row_label'),
                        null,
                        chip: _row!['status']?.toString() ?? 'pending',
                      ),
                      _buildInfo(
                        t('maintenance_detail_priority_row_label'),
                        _priorityLabels()[_row!['priority']?.toString()] ?? '—',
                      ),
                      _buildInfo(
                        t('maintenance_detail_requester_label'),
                        _row!['requester_name']?.toString() ?? '—',
                      ),
                      _buildInfo(
                        t('maintenance_detail_assigned_label'),
                        _row!['assigned_name']?.toString() ?? '—',
                      ),
                      if (_row!['work_status'] != null)
                        _buildInfo(
                          t('maintenance_detail_work_status_row_label'),
                          _workStatusLabels()[_row!['work_status']
                                  ?.toString()] ??
                              '—',
                        ),
                      if (_row!['description'] != null &&
                          _row!['description'].toString().isNotEmpty)
                        _buildInfo(
                          t('maintenance_detail_notes_label'),
                          _row!['description']?.toString() ?? '—',
                        ),
                      if (_row!['resolution_notes'] != null &&
                          _row!['resolution_notes'].toString().isNotEmpty)
                        _buildInfo(
                          t('maintenance_detail_resolution_notes_label'),
                          _row!['resolution_notes']?.toString() ?? '—',
                        ),
                      if (_row!['cost'] != null &&
                          _row!['cost'].toString().isNotEmpty)
                        _buildInfo(
                          t('maintenance_detail_cost_row_label'),
                          _row!['cost']?.toString() ?? '—',
                        ),
                      if (_row!['image_path'] != null)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(12),
                            child: Image.network(
                              '${AppConfig.baseUrl}${_row!['image_path']}',
                              height: 200,
                              fit: BoxFit.cover,
                              errorBuilder: (ctx, err, stack) => Text(
                                t('maintenance_detail_image_load_error'),
                              ),
                            ),
                          ),
                        ),
                      const SizedBox(height: 16),
                      _buildActions(),
                    ],
                  ),
                ),
        ),
      ),
    );
  }

  Widget _buildInfo(String label, String? value, {String? chip}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('$label: ', style: const TextStyle(fontWeight: FontWeight.bold)),
          Expanded(
            child: chip != null
                ? Chip(
                    label: Text(_statusLabels()[chip] ?? chip),
                    backgroundColor: (_statusColors[chip] ?? Colors.grey)
                        .withValues(alpha: 0.15),
                    labelStyle: TextStyle(
                      color: _statusColors[chip] ?? Colors.grey,
                    ),
                  )
                : Text(value ?? '—'),
          ),
        ],
      ),
    );
  }

  Widget _buildActions() {
    final status = _row!['status']?.toString() ?? 'pending';
    final isManager = _row!['is_maintenance_manager'] == true;
    final isAssignedToMe = _row!['is_assigned_to_me'] == true;
    final isRequester = _row!['is_requester'] == true;
    final children = <Widget>[];

    if (isManager && status == 'pending') {
      children.add(
        ElevatedButton.icon(
          onPressed: _assign,
          icon: const Icon(Icons.person_add),
          label: Text(t('maintenance_detail_assign_button')),
        ),
      );
    }
    if (isAssignedToMe && status == 'assigned') {
      children.add(
        ElevatedButton.icon(
          onPressed: () => _transition('start'),
          icon: const Icon(Icons.play_arrow),
          label: Text(t('maintenance_detail_start_button')),
        ),
      );
    }
    if (isAssignedToMe && status == 'in_progress') {
      children.add(
        ElevatedButton.icon(
          onPressed: _updateWorkStatus,
          icon: const Icon(Icons.check),
          label: Text(t('maintenance_detail_update_status_button')),
        ),
      );
    }
    if (isRequester && status == 'completed') {
      children.add(
        ElevatedButton.icon(
          onPressed: () => _transition('confirm'),
          icon: const Icon(Icons.thumb_up),
          label: Text(t('maintenance_detail_confirm_button')),
        ),
      );
    }
    if (children.isEmpty) return const SizedBox.shrink();
    return Wrap(spacing: 12, runSpacing: 12, children: children);
  }
}
