import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/network/api_client.dart';
import '../../core/utils/notification_router.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';

/// صندوق البريد الخاص بالمستخدم (البريد/Mail): يعرض كل الرسائل الإلكترونية
/// المُرسلة من النظام والموجّهة تحديداً لبريد المستخدم الحالي (من حساب
/// المستخدم أو من ملفه الوظيفي)، متاح لكل مستخدمي التطبيق في جميع الأقسام
/// بلا أي شرط صلاحية.
class MailScreen extends StatefulWidget {
  const MailScreen({super.key});

  @override
  State<MailScreen> createState() => _MailScreenState();
}

class _MailScreenState extends State<MailScreen> {
  bool _loading = true;
  String? _error;
  String? _offlineNotice;
  List<dynamic> _items = [];
  bool _noEmail = false;

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
      final res = await repo.getJson('/mail', 'mail');
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      setState(() {
        _items = (body['data'] as List?) ?? [];
        _offlineNotice = res.fromCache ? res.errorMessage : null;
        _noEmail = (body['has_email'] as bool?) == false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _error = AppStrings.t(
          'mail_load_error',
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
      await api.post('/mail/$id/read');
    } catch (_) {}
  }

  Future<void> _markAllRead() async {
    final api = context.read<ApiClient>();
    try {
      await api.post('/mail/read-all');
      if (!mounted) return;
      setState(() {
        for (final m in _items) {
          (m as Map)['opened_at'] = (m)['opened_at'] ?? DateTime.now().toIso8601String();
        }
      });
    } catch (_) {}
  }

  String _statusLabel(String? status, String Function(String) t) {
    switch (status) {
      case 'sent':
        return t('mail_status_sent');
      case 'failed':
        return t('mail_status_failed');
      default:
        return t('mail_status_pending');
    }
  }

  void _showDetail(Map<String, dynamic> m, String Function(String) t) async {
    final id = m['id'] as int?;
    if (id != null && (m['opened_at'] == null)) {
      await _markRead(id);
      setState(() => m['opened_at'] = DateTime.now().toIso8601String());
    }
    if (!mounted) return;
    final entityType = m['entity_type']?.toString();
    final actionType = m['action_type']?.toString();
    final link = m['link']?.toString();
    final targetRoute = resolveNotificationRoute(
      entityType: entityType,
      actionType: actionType,
      link: link,
    );

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Row(
          children: [
            const Icon(Icons.mail_outline),
            const SizedBox(width: 8),
            Expanded(
              child: Text(m['subject']?.toString() ?? t('mail_detail_title')),
            ),
          ],
        ),
        content: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                m['body']?.toString() ?? '',
                style: const TextStyle(fontSize: 15),
              ),
              const Divider(height: 24),
              _detailRow(t('mail_to_label'), m['recipient']?.toString() ?? ''),
              _detailRow(
                t('mail_date_label'),
                m['created_at']?.toString() ?? '',
              ),
              _detailRow(
                t('mail_status_label'),
                _statusLabel(m['status']?.toString(), t),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: Text(t('mail_close')),
          ),
          if (targetRoute != '/notifications')
            ElevatedButton(
              onPressed: () {
                Navigator.of(context).pop();
                context.go(targetRoute);
              },
              child: Text(t('mail_open')),
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

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final unread = _items
        .cast<Map>()
        .where((m) => m['opened_at'] == null)
        .length;
    return Scaffold(
      appBar: AppBar(
        title: Text(t('mail_title')),
        actions: [
          if (unread > 0)
            IconButton(
              icon: const Icon(Icons.mark_email_read_outlined),
              tooltip: t('mail_mark_all_read'),
              onPressed: _markAllRead,
            ),
        ],
      ),
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
                child: _items.isEmpty
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: _noEmail
                              ? Column(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const Icon(
                                      Icons.mail_outline,
                                      size: 48,
                                      color: Colors.grey,
                                    ),
                                    const SizedBox(height: 12),
                                    Text(
                                      t('mail_no_email_title'),
                                      textAlign: TextAlign.center,
                                      style: const TextStyle(
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                    const SizedBox(height: 6),
                                    Text(
                                      t('mail_no_email_body'),
                                      textAlign: TextAlign.center,
                                      style: const TextStyle(color: Colors.grey),
                                    ),
                                  ],
                                )
                              : Text(t('mail_empty')),
                        ),
                      )
                    : ListView.separated(
                        itemCount: _items.length,
                        separatorBuilder: (a, b) => const Divider(height: 1),
                        itemBuilder: (context, i) {
                          final m = _items[i] as Map;
                          final read = m['opened_at'] != null;
                          return ListTile(
                            leading: Icon(
                              read ? Icons.mail_outline : Icons.mail,
                              color: read
                                  ? Colors.grey
                                  : Theme.of(context).colorScheme.primary,
                            ),
                            title: Text(
                              m['subject']?.toString() ?? '',
                              style: TextStyle(
                                fontWeight: read
                                    ? FontWeight.normal
                                    : FontWeight.bold,
                              ),
                            ),
                            subtitle: Text(
                              m['body']?.toString() ?? '',
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                            ),
                            trailing: Text(
                              m['created_at']?.toString().split(' ').first ??
                                  '',
                              style: const TextStyle(
                                fontSize: 11,
                                color: Colors.grey,
                              ),
                            ),
                            onTap: () =>
                                _showDetail(m.cast<String, dynamic>(), t),
                          );
                        },
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
