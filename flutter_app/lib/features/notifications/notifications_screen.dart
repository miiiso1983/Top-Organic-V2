import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/network/api_client.dart';
import '../../core/utils/notification_router.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';
import 'notifications_provider.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  bool _loading = true;
  String? _error;
  String? _offlineNotice;
  List<dynamic> _items = [];

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
      final res = await repo.getJson('/notifications', 'notifications');
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      setState(() {
        _items = (body['data'] as List?) ?? [];
        _offlineNotice = res.fromCache ? res.errorMessage : null;
      });
      context.read<NotificationsProvider>().setCount(
        (body['unread'] as num?)?.toInt() ?? 0,
      );
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _error = AppStrings.t(
          'notifications_load_error',
          context.read<LocaleProvider>().locale.languageCode,
        ),
      );
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _markRead(int id) async {
    final api = context.read<ApiClient>();
    try {
      await api.post('/notifications/$id/read');
      if (mounted) {
        final provider = context.read<NotificationsProvider>();
        provider.setCount((provider.unreadCount - 1).clamp(0, 1 << 30));
      }
    } catch (_) {}
  }

  void _showDetail(Map<String, dynamic> n, String Function(String) t) async {
    final id = n['id'] as int?;
    if (id != null && (n['is_read'] ?? 0) == 0) {
      await _markRead(id);
      setState(() => n['is_read'] = 1);
    }
    if (!mounted) return;
    final metadata =
        n['metadata_json'] is String &&
            (n['metadata_json'] as String).isNotEmpty
        ? (jsonDecode(n['metadata_json'] as String) as Map<String, dynamic>?)
        : (n['metadata_json'] as Map<String, dynamic>?);
    final entityType = n['entity_type']?.toString();
    final link = n['link']?.toString();
    final actionType = n['action_type']?.toString();
    final templateCode = metadata?['template_code']?.toString();
    final createdAt = n['created_at']?.toString() ?? '';
    final targetRoute = resolveNotificationRoute(
      entityType: entityType,
      actionType: actionType,
      link: link,
      templateCode: templateCode,
    );

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Row(
          children: [
            Icon(
              actionType == 'pending_approval' ||
                      actionType == 'pending_reminder' ||
                      actionType == 'pending_reminder_manager'
                  ? Icons.pending_actions
                  : actionType == 'approved'
                  ? Icons.check_circle
                  : actionType == 'step_approved'
                  ? Icons.check_circle_outline
                  : actionType == 'rejected'
                  ? Icons.cancel
                  : actionType == 'returned_for_edit'
                  ? Icons.edit_note
                  : Icons.notifications,
              color: Theme.of(context).colorScheme.primary,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                n['title']?.toString() ?? t('notifications_detail_title'),
              ),
            ),
          ],
        ),
        content: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                n['body']?.toString() ?? '',
                style: const TextStyle(fontSize: 15),
              ),
              const SizedBox(height: 16),
              if (metadata != null) ...[
                const Divider(),
                if (metadata['template_name'] != null)
                  _detailRow(
                    t('notifications_request_type'),
                    metadata['template_name'].toString(),
                  ),
                if (metadata['request_no'] != null)
                  _detailRow(
                    t('notifications_request_no'),
                    metadata['request_no'].toString(),
                  ),
                if (metadata['requester_name'] != null)
                  _detailRow(
                    t('notifications_requester'),
                    metadata['requester_name'].toString(),
                  ),
                if (metadata['hours_pending'] != null)
                  _detailRow(
                    t('notifications_hours_pending'),
                    '${metadata['hours_pending']} ${t('notifications_hours_suffix')}',
                  ),
                if (metadata['status'] != null)
                  _detailRow(
                    t('notifications_status'),
                    metadata['status'].toString(),
                  ),
                if (metadata['step_title'] != null)
                  _detailRow(
                    t('notifications_step'),
                    metadata['step_title'].toString(),
                  ),
              ],
              const Divider(),
              _detailRow(t('notifications_date'), createdAt),
              if (actionType != null)
                _detailRow(
                  t('notifications_type'),
                  _actionTypeLabel(actionType, t),
                ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: Text(t('notifications_close')),
          ),
          if (targetRoute != '/notifications')
            ElevatedButton(
              onPressed: () {
                Navigator.of(context).pop();
                context.go(targetRoute);
              },
              child: Text(t('notifications_open')),
            ),
        ],
      ),
    );
  }

  Widget _detailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('$label: ', style: const TextStyle(fontWeight: FontWeight.bold)),
          Expanded(child: Text(value)),
        ],
      ),
    );
  }

  String _actionTypeLabel(String actionType, String Function(String) t) {
    return {
          'pending_approval': t('notifications_action_pending_approval'),
          'pending_reminder': t('notifications_action_pending_reminder'),
          'pending_reminder_manager': t(
            'notifications_action_pending_reminder_manager',
          ),
          'approved': t('notifications_action_approved'),
          'step_approved': t('notifications_action_step_approved'),
          'rejected': t('notifications_action_rejected'),
          'returned_for_edit': t('notifications_action_returned_for_edit'),
          'escalated': t('notifications_action_escalated'),
          'status_update': t('notifications_action_status_update'),
        }[actionType] ??
        actionType;
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
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
              child: _items.isEmpty
                  ? Center(child: Text(t('notifications_empty')))
                  : ListView.separated(
                      itemCount: _items.length,
                      separatorBuilder: (a, b) => const Divider(height: 1),
                      itemBuilder: (context, i) {
                        final n = _items[i] as Map;
                        final read = (n['is_read'] ?? 0) == 1;
                        return ListTile(
                          leading: Icon(
                            Icons.notifications,
                            color: read
                                ? Colors.grey
                                : Theme.of(context).colorScheme.primary,
                          ),
                          title: Text(
                            n['title']?.toString() ?? '',
                            style: TextStyle(
                              fontWeight: read
                                  ? FontWeight.normal
                                  : FontWeight.bold,
                            ),
                          ),
                          subtitle: Text(
                            n['body']?.toString() ?? '',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                          trailing: Text(
                            n['created_at']?.toString().split(' ').first ?? '',
                            style: const TextStyle(
                              fontSize: 11,
                              color: Colors.grey,
                            ),
                          ),
                          onTap: () =>
                              _showDetail(n.cast<String, dynamic>(), t),
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
