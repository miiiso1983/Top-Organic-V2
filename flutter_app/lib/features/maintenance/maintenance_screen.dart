import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';

class MaintenanceScreen extends StatefulWidget {
  const MaintenanceScreen({super.key});

  @override
  State<MaintenanceScreen> createState() => _MaintenanceScreenState();
}

class _MaintenanceScreenState extends State<MaintenanceScreen> {
  bool _loading = true;
  String? _error;
  String? _offlineNotice;
  List<dynamic> _requests = [];

  Map<String, String> _statusLabels(String Function(String) t) => {
    'pending': t('maintenance_list_status_pending'),
    'assigned': t('maintenance_list_status_assigned'),
    'in_progress': t('maintenance_list_status_in_progress'),
    'completed': t('maintenance_list_status_completed'),
    'confirmed': t('maintenance_list_status_confirmed'),
    'cancelled': t('maintenance_list_status_cancelled'),
  };

  static const _statusColors = {
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

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson('/maintenance', 'maintenance');
      if (!mounted) return;
      final data = res.data as Map<String, dynamic>;
      setState(() {
        _requests = (data['data'] as List?) ?? [];
        _offlineNotice = res.fromCache ? res.errorMessage : null;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(
        () => _error = AppStrings.t('maintenance_list_load_error', lang),
      );
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final statusLabels = _statusLabels(t);
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _load,
        child: Column(
          children: [
            OfflineBanner(message: _offlineNotice),
            Expanded(
              child: AsyncStateView(
                loading: _loading,
                error: _error,
                onRetry: _load,
                child: _requests.isEmpty
                    ? Center(child: Text(t('maintenance_list_empty')))
                    : ListView.builder(
                        padding: const EdgeInsets.all(12),
                        itemCount: _requests.length,
                        itemBuilder: (context, i) {
                          final r = _requests[i] as Map;
                          final status = r['status']?.toString() ?? 'pending';
                          return Card(
                            child: ListTile(
                              onTap: () =>
                                  context.push('/maintenance/${r['id']}'),
                              title: Text(r['title']?.toString() ?? '—'),
                              subtitle: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    '${t('maintenance_list_device_label')}${r['device_name'] ?? '—'}',
                                  ),
                                  Text(
                                    '${t('maintenance_list_requester_label')}${r['requester_name'] ?? '—'}',
                                  ),
                                  if (r['assigned_name'] != null)
                                    Text(
                                      '${t('maintenance_list_assigned_label')}${r['assigned_name']}',
                                    ),
                                ],
                              ),
                              trailing: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Chip(
                                    label: Text(statusLabels[status] ?? status),
                                    backgroundColor: _statusColors[status]
                                        ?.withValues(alpha: 0.15),
                                    labelStyle: TextStyle(
                                      color: _statusColors[status],
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
              ),
            ),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('/maintenance/create'),
        icon: const Icon(Icons.add),
        label: Text(t('maintenance_list_fab_label')),
      ),
    );
  }
}
