import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';
import 'workflow_details_screen.dart';

/// شاشة الأرشيف: تعرض طلبات الموظف التي تم البت فيها (معتمدة أو مرفوضة)
/// بعد صدور قرار نهائي بشأنها وإرسال الإشعار للمستخدم.
class ArchiveScreen extends StatefulWidget {
  const ArchiveScreen({super.key});

  @override
  State<ArchiveScreen> createState() => _ArchiveScreenState();
}

/// عنصر أرشيف موحّد (طلب موافقة عادي أو طلب صيانة)
class _ArchiveEntry {
  const _ArchiveEntry({required this.data, this.isMaintenance = false});
  final Map data;
  final bool isMaintenance;
}

class _ArchiveScreenState extends State<ArchiveScreen> {
  bool _loading = true;
  String? _error;
  List<_ArchiveEntry> _archived = [];

  String _statusLabel(String status, String Function(String) t) {
    return {
          'approved': t('archive_status_approved'),
          'rejected': t('archive_status_rejected'),
        }[status] ??
        status;
  }

  static const _statusColors = {
    'approved': Colors.green,
    'rejected': Colors.red,
  };

  String _maintStatusLabel(String status, String Function(String) t) {
    return {
          'confirmed': t('archive_maint_status_confirmed'),
          'cancelled': t('archive_maint_status_cancelled'),
        }[status] ??
        status;
  }

  static const _maintStatusColors = {
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

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<ApiClient>();
      final res = await api.get('/workflows/my-requests');
      final body = res.data as Map<String, dynamic>;
      final all = (body['data'] as List?) ?? [];
      List<dynamic> maintenance = [];
      try {
        final maintRes = await api.get('/maintenance');
        final maintBody = maintRes.data as Map<String, dynamic>;
        maintenance = (maintBody['data'] as List?) ?? [];
      } catch (_) {
        // تعذر جلب طلبات الصيانة لا يجب أن يعطّل الأرشيف
      }
      if (!mounted) return;
      setState(() {
        _archived = [
          ...all
              .where((r) {
                final status = (r as Map)['status']?.toString() ?? '';
                return status == 'approved' || status == 'rejected';
              })
              .map((r) => _ArchiveEntry(data: r as Map)),
          ...maintenance
              .where((r) {
                final m = r as Map;
                if (m['is_requester'] != true) return false;
                final status = m['status']?.toString() ?? '';
                return status == 'confirmed' || status == 'cancelled';
              })
              .map((r) => _ArchiveEntry(data: r as Map, isMaintenance: true)),
        ];
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      String message = AppStrings.t('archive_load_error', lang);
      if (e is DioException && e.response?.data is Map) {
        message = (e.response!.data as Map)['error']?.toString() ?? message;
      }
      setState(() {
        _error = message;
        _loading = false;
      });
    }
  }

  Future<void> _openDetails(int id) async {
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => WorkflowDetailsScreen(requestId: id)),
    );
  }

  Future<void> _openMaintenanceDetails(int id) async {
    await context.push('/maintenance/$id');
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

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(title: Text(t('archive_title'))),
      body: AsyncStateView(
        loading: _loading,
        error: _error,
        onRetry: _load,
        child: RefreshIndicator(
          onRefresh: _load,
          child: _archived.isEmpty
              ? ListView(
                  children: [
                    Padding(
                      padding: const EdgeInsets.all(32),
                      child: Center(child: Text(t('archive_empty'))),
                    ),
                  ],
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: _archived.length,
                  itemBuilder: (context, i) {
                    final entry = _archived[i];
                    final r = entry.data;
                    final status = r['status']?.toString() ?? '';
                    final id = _toInt(r['id']);
                    if (entry.isMaintenance) {
                      return Card(
                        child: ListTile(
                          leading: _buildMaintStatusChip(status, t),
                          title: Text(
                            r['title']?.toString() ??
                                t('archive_maintenance_fallback_title'),
                          ),
                          subtitle: Text(
                            '${t('archive_requester_label')}${r['requester_name']?.toString() ?? '-'}',
                          ),
                          onTap: () => _openMaintenanceDetails(id),
                        ),
                      );
                    }
                    return Card(
                      child: ListTile(
                        leading: _buildStatusChip(status, t),
                        title: Text(
                          r['template_name']?.toString() ??
                              r['title']?.toString() ??
                              t('archive_request_fallback_title'),
                        ),
                        subtitle: Text(
                          '${t('archive_number_label')}${r['request_no']?.toString() ?? '#${r['id']}'}${t('archive_amount_label')}${r['amount'] ?? 0}',
                        ),
                        onTap: () => _openDetails(id),
                      ),
                    );
                  },
                ),
        ),
      ),
    );
  }
}
