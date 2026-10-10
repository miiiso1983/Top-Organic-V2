import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';
import 'compose_mail_screen.dart';

/// البريد الداخلي: رسائل حقيقية بين مستخدمي النظام (إرسال/استقبال/رد)،
/// متاح لكل مستخدمي التطبيق في جميع الأقسام بلا أي شرط صلاحية.
class MailScreen extends StatefulWidget {
  const MailScreen({super.key});

  @override
  State<MailScreen> createState() => _MailScreenState();
}

class _MailScreenState extends State<MailScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  bool _loadingInbox = true;
  bool _loadingSent = true;
  String? _inboxError;
  String? _sentError;
  String? _offlineNotice;
  List<dynamic> _inbox = [];
  List<dynamic> _sent = [];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadInbox();
    _loadSent();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadInbox() async {
    setState(() {
      _loadingInbox = true;
      _inboxError = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson('/mail', 'mail_inbox');
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      setState(() {
        _inbox = (body['data'] as List?) ?? [];
        _offlineNotice = res.fromCache ? res.errorMessage : null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _inboxError = AppStrings.t(
          'mail_load_error',
          context.read<LocaleProvider>().locale.languageCode,
        ),
      );
    } finally {
      if (mounted) setState(() => _loadingInbox = false);
    }
  }

  Future<void> _loadSent() async {
    setState(() {
      _loadingSent = true;
      _sentError = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson('/mail/sent', 'mail_sent');
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      setState(() => _sent = (body['data'] as List?) ?? []);
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _sentError = AppStrings.t(
          'mail_load_error',
          context.read<LocaleProvider>().locale.languageCode,
        ),
      );
    } finally {
      if (mounted) setState(() => _loadingSent = false);
    }
  }

  Future<void> _markRead(int id) async {
    final api = context.read<ApiClient>();
    try {
      await api.post('/mail/$id/read');
    } catch (_) {}
  }

  Future<void> _deleteInbox(int id) async {
    final api = context.read<ApiClient>();
    try {
      await api.post('/mail/$id/delete');
      if (!mounted) return;
      setState(() => _inbox.removeWhere((m) => (m as Map)['id'] == id));
    } catch (_) {}
  }

  Future<void> _deleteSent(int id) async {
    final api = context.read<ApiClient>();
    try {
      await api.post('/mail/sent/$id/delete');
      if (!mounted) return;
      setState(() => _sent.removeWhere((m) => (m as Map)['id'] == id));
    } catch (_) {}
  }

  Future<bool> _confirmDelete(String Function(String) t) async {
    final result = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('mail_delete_confirm_title')),
        content: Text(t('mail_delete_confirm_body')),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(t('mail_close')),
          ),
          ElevatedButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            child: Text(t('mail_delete_btn')),
          ),
        ],
      ),
    );
    return result ?? false;
  }

  void _openCompose({int? replyToId, String? replyToName, String? subject}) async {
    final sent = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => ComposeMailScreen(
          initialRecipientId: replyToId,
          initialRecipientName: replyToName,
          initialSubject: subject,
        ),
      ),
    );
    if (sent == true) {
      _loadSent();
      _tabController.animateTo(1);
    }
  }

  void _showInboxDetail(Map<String, dynamic> m, String Function(String) t) async {
    final id = m['id'] as int?;
    if (id != null && m['read_at'] == null) {
      await _markRead(id);
      setState(() => m['read_at'] = DateTime.now().toIso8601String());
    }
    if (!mounted) return;
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Row(
          children: [
            const Icon(Icons.mail_outline),
            const SizedBox(width: 8),
            Expanded(child: Text(m['subject']?.toString() ?? '')),
          ],
        ),
        content: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              _detailRow(t('mail_from_label'), m['sender_name']?.toString() ?? ''),
              _detailRow(t('mail_date_label'), m['created_at']?.toString() ?? ''),
              const Divider(height: 24),
              Text(m['body']?.toString() ?? '', style: const TextStyle(fontSize: 15)),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () async {
              if (id == null) return;
              Navigator.of(context).pop();
              if (await _confirmDelete(t)) _deleteInbox(id);
            },
            child: Text(t('mail_delete_btn'), style: const TextStyle(color: Colors.red)),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: Text(t('mail_close')),
          ),
          ElevatedButton.icon(
            onPressed: () {
              Navigator.of(context).pop();
              _openCompose(
                replyToId: m['sender_id'] as int?,
                replyToName: m['sender_name']?.toString(),
                subject: 'Re: ${m['subject'] ?? ''}',
              );
            },
            icon: const Icon(Icons.reply, size: 18),
            label: Text(t('mail_reply_btn')),
          ),
        ],
      ),
    );
  }

  void _showSentDetail(Map<String, dynamic> m, String Function(String) t) {
    final id = m['id'] as int?;
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Row(
          children: [
            const Icon(Icons.send_outlined),
            const SizedBox(width: 8),
            Expanded(child: Text(m['subject']?.toString() ?? '')),
          ],
        ),
        content: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              _detailRow(t('mail_to_label'), m['recipients_names']?.toString() ?? ''),
              _detailRow(t('mail_date_label'), m['created_at']?.toString() ?? ''),
              const Divider(height: 24),
              Text(m['body']?.toString() ?? '', style: const TextStyle(fontSize: 15)),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () async {
              if (id == null) return;
              Navigator.of(context).pop();
              if (await _confirmDelete(t)) _deleteSent(id);
            },
            child: Text(t('mail_delete_btn'), style: const TextStyle(color: Colors.red)),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: Text(t('mail_close')),
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
    final unread = _inbox.cast<Map>().where((m) => m['read_at'] == null).length;

    return Scaffold(
      appBar: AppBar(
        title: Text(t('mail_title')),
        bottom: TabBar(
          controller: _tabController,
          tabs: [
            Tab(text: unread > 0 ? '${t('mail_inbox_tab')} ($unread)' : t('mail_inbox_tab')),
            Tab(text: t('mail_sent_tab')),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openCompose(),
        icon: const Icon(Icons.edit),
        label: Text(t('mail_compose_fab')),
      ),
      body: Column(
        children: [
          OfflineBanner(message: _offlineNotice),
          Expanded(
            child: TabBarView(
              controller: _tabController,
              children: [
                RefreshIndicator(
                  onRefresh: _loadInbox,
                  child: AsyncStateView(
                    loading: _loadingInbox,
                    error: _inboxError,
                    onRetry: _loadInbox,
                    child: _inbox.isEmpty
                        ? ListView(
                            children: [
                              Padding(
                                padding: const EdgeInsets.all(40),
                                child: Center(child: Text(t('mail_empty'))),
                              ),
                            ],
                          )
                        : ListView.separated(
                            itemCount: _inbox.length,
                            separatorBuilder: (a, b) => const Divider(height: 1),
                            itemBuilder: (context, i) {
                              final m = _inbox[i] as Map;
                              final read = m['read_at'] != null;
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
                                    fontWeight: read ? FontWeight.normal : FontWeight.bold,
                                  ),
                                ),
                                subtitle: Text(
                                  '${m['sender_name'] ?? ''} — ${m['body'] ?? ''}',
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                ),
                                trailing: Text(
                                  m['created_at']?.toString().split(' ').first ?? '',
                                  style: const TextStyle(fontSize: 11, color: Colors.grey),
                                ),
                                onTap: () => _showInboxDetail(m.cast<String, dynamic>(), t),
                              );
                            },
                          ),
                  ),
                ),
                RefreshIndicator(
                  onRefresh: _loadSent,
                  child: AsyncStateView(
                    loading: _loadingSent,
                    error: _sentError,
                    onRetry: _loadSent,
                    child: _sent.isEmpty
                        ? ListView(
                            children: [
                              Padding(
                                padding: const EdgeInsets.all(40),
                                child: Center(child: Text(t('mail_sent_empty'))),
                              ),
                            ],
                          )
                        : ListView.separated(
                            itemCount: _sent.length,
                            separatorBuilder: (a, b) => const Divider(height: 1),
                            itemBuilder: (context, i) {
                              final m = _sent[i] as Map;
                              return ListTile(
                                leading: const Icon(Icons.send_outlined, color: Colors.grey),
                                title: Text(m['subject']?.toString() ?? ''),
                                subtitle: Text(
                                  '${t('mail_to_label')}: ${m['recipients_names'] ?? ''}',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                                trailing: Text(
                                  m['created_at']?.toString().split(' ').first ?? '',
                                  style: const TextStyle(fontSize: 11, color: Colors.grey),
                                ),
                                onTap: () => _showSentDetail(m.cast<String, dynamic>(), t),
                              );
                            },
                          ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
