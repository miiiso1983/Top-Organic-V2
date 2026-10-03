import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../shared/widgets/offline_banner.dart';

class TasksScreen extends StatefulWidget {
  const TasksScreen({super.key});

  @override
  State<TasksScreen> createState() => _TasksScreenState();
}

class _TasksScreenState extends State<TasksScreen> {
  bool _loading = true;
  String? _error;
  String? _offlineNotice;
  List<dynamic> _tasks = [];

  static const _statuses = ['open', 'in_progress', 'done', 'cancelled'];
  static const _statusLabels = {
    'open': 'مفتوحة',
    'in_progress': 'قيد التنفيذ',
    'done': 'مكتملة',
    'cancelled': 'ملغاة',
  };
  static const _statusColors = {
    'open': Colors.grey,
    'in_progress': Colors.orange,
    'done': Colors.green,
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
      final res = await repo.getJson('/tasks', 'tasks');
      if (!mounted) return;
      setState(() {
        _tasks = ((res.data as Map)['data'] as List?) ?? [];
        _offlineNotice = res.fromCache ? res.errorMessage : null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = 'تعذر تحميل المهام');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _updateStatus(int id, String status) async {
    final repo = context.read<CachedRepository>();
    final res = await repo.postOrQueue('/tasks/$id/status', {
      'status': status,
    }, entity: 'tasks');
    if (mounted && res.errorMessage != null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(res.errorMessage!)));
    }
    _load();
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: _load,
      child: Column(
        children: [
          OfflineBanner(message: _offlineNotice),
          Expanded(
            child: AsyncStateView(
              loading: _loading,
              error: _error,
              onRetry: _load,
              child: _tasks.isEmpty
                  ? const Center(child: Text('لا توجد مهام'))
                  : ListView.builder(
                      padding: const EdgeInsets.all(12),
                      itemCount: _tasks.length,
                      itemBuilder: (context, i) {
                        final t = _tasks[i] as Map;
                        final status = t['status']?.toString() ?? 'open';
                        return Card(
                          child: ListTile(
                            title: Text(t['title'].toString()),
                            subtitle: Text(
                              'المسند إليه: ${t['assignee'] ?? ''} • الاستحقاق: ${t['due_date'] ?? '-'}',
                            ),
                            trailing: DropdownButton<String>(
                              value: status,
                              underline: const SizedBox(),
                              items: _statuses
                                  .map(
                                    (s) => DropdownMenuItem(
                                      value: s,
                                      child: Text(
                                        _statusLabels[s]!,
                                        style: TextStyle(
                                          color: _statusColors[s],
                                        ),
                                      ),
                                    ),
                                  )
                                  .toList(),
                              onChanged: (s) {
                                if (s != null) _updateStatus(t['id'] as int, s);
                              },
                            ),
                          ),
                        );
                      },
                    ),
            ),
          ),
        ],
      ),
    );
  }
}
