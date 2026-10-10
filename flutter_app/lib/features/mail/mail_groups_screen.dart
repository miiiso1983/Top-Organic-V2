import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';

/// إدارة مجموعات البريد (قوائم توزيع مخصّصة) — أي مستخدم يمكنه إنشاء مجموعة
/// واستخدامها عند الإرسال، لكن التعديل والحذف مقصوران على منشئها فقط.
class MailGroupsScreen extends StatefulWidget {
  const MailGroupsScreen({super.key});

  @override
  State<MailGroupsScreen> createState() => _MailGroupsScreenState();
}

class _MailGroupsScreenState extends State<MailGroupsScreen> {
  bool _loading = true;
  String? _error;
  List<dynamic> _groups = [];
  List<dynamic> _users = [];

  String t(String key) =>
      AppStrings.t(key, context.read<LocaleProvider>().locale.languageCode);

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
      final results = await Future.wait([
        api.get('/mail/groups'),
        api.get('/mail/users'),
      ]);
      final groupsBody = results[0].data as Map<String, dynamic>;
      final usersBody = results[1].data as Map<String, dynamic>;
      if (!mounted) return;
      setState(() {
        _groups = (groupsBody['data'] as List?) ?? [];
        _users = (usersBody['data'] as List?) ?? [];
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = t('mail_groups_load_error'));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openForm({Map? group}) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => _GroupFormDialog(group: group, users: _users),
    );
    if (saved == true) _load();
  }

  Future<void> _delete(int id) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('mail_group_delete_confirm_title')),
        content: Text(t('mail_group_delete_confirm_body')),
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
    if (confirmed != true || !mounted) return;
    try {
      final api = context.read<ApiClient>();
      await api.post('/mail/groups/$id/delete');
      if (!mounted) return;
      setState(() => _groups.removeWhere((g) => (g as Map)['id'] == id));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('mail_group_delete_error'))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String tt(String key) => AppStrings.t(key, lang);

    return Scaffold(
      appBar: AppBar(title: Text(tt('mail_groups_screen_title'))),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openForm(),
        icon: const Icon(Icons.add),
        label: Text(tt('mail_group_new_fab')),
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? Center(child: Text(_error!))
                : _groups.isEmpty
                    ? ListView(
                        children: [
                          Padding(
                            padding: const EdgeInsets.all(40),
                            child: Center(child: Text(tt('mail_groups_empty'))),
                          ),
                        ],
                      )
                    : ListView.separated(
                        itemCount: _groups.length,
                        separatorBuilder: (_, _) => const Divider(height: 1),
                        itemBuilder: (context, i) {
                          final g = _groups[i] as Map;
                          final isMine = g['is_mine'] == true;
                          return ListTile(
                            leading: const Icon(Icons.group_outlined),
                            title: Text(g['name']?.toString() ?? ''),
                            subtitle: Text(
                              '${g['members_count'] ?? 0} ${tt('mail_group_members_count')}',
                            ),
                            trailing: isMine
                                ? Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      IconButton(
                                        icon: const Icon(Icons.edit, size: 20),
                                        onPressed: () => _openForm(group: g),
                                      ),
                                      IconButton(
                                        icon: const Icon(Icons.delete, size: 20, color: Colors.red),
                                        onPressed: () => _delete(g['id'] as int),
                                      ),
                                    ],
                                  )
                                : null,
                          );
                        },
                      ),
      ),
    );
  }
}

/// نافذة إنشاء/تعديل مجموعة بريد
class _GroupFormDialog extends StatefulWidget {
  const _GroupFormDialog({required this.group, required this.users});

  final Map? group;
  final List<dynamic> users;

  @override
  State<_GroupFormDialog> createState() => _GroupFormDialogState();
}

class _GroupFormDialogState extends State<_GroupFormDialog> {
  late final TextEditingController _nameController;
  final Set<int> _memberIds = {};
  bool _saving = false;
  bool _loadingDetail = false;

  bool get _isEdit => widget.group != null;

  String t(String key) =>
      AppStrings.t(key, context.read<LocaleProvider>().locale.languageCode);

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController(text: widget.group?['name']?.toString() ?? '');
    if (_isEdit) _loadDetail();
  }

  @override
  void dispose() {
    _nameController.dispose();
    super.dispose();
  }

  Future<void> _loadDetail() async {
    setState(() => _loadingDetail = true);
    try {
      final api = context.read<ApiClient>();
      final res = await api.get('/mail/groups/${widget.group!['id']}');
      final body = res.data as Map<String, dynamic>;
      final data = body['data'] as Map<String, dynamic>?;
      final ids = ((data?['member_ids'] as List?) ?? []).cast<int>();
      if (!mounted) return;
      setState(() => _memberIds.addAll(ids));
    } catch (_) {
    } finally {
      if (mounted) setState(() => _loadingDetail = false);
    }
  }

  Future<void> _save() async {
    final name = _nameController.text.trim();
    if (name.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('mail_group_name_required'))),
      );
      return;
    }
    setState(() => _saving = true);
    try {
      final api = context.read<ApiClient>();
      final data = {'name': name, 'member_ids': _memberIds.toList()};
      if (_isEdit) {
        await api.post('/mail/groups/${widget.group!['id']}', data: data);
      } else {
        await api.post('/mail/groups', data: data);
      }
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      String message = t('mail_group_save_error');
      if (e is DioException && e.response?.data is Map) {
        message = (e.response!.data as Map)['error']?.toString() ?? message;
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(_isEdit ? t('mail_group_edit_title') : t('mail_group_new_title')),
      content: SizedBox(
        width: 360,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            TextField(
              controller: _nameController,
              decoration: InputDecoration(
                labelText: t('mail_group_name_label'),
                border: const OutlineInputBorder(),
                isDense: true,
              ),
            ),
            const SizedBox(height: 12),
            Text(t('mail_group_members_label'), style: const TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 6),
            SizedBox(
              height: 260,
              child: _loadingDetail
                  ? const Center(child: CircularProgressIndicator())
                  : ListView.builder(
                      itemCount: widget.users.length,
                      itemBuilder: (context, i) {
                        final u = widget.users[i] as Map;
                        final id = u['id'] as int;
                        return CheckboxListTile(
                          dense: true,
                          value: _memberIds.contains(id),
                          title: Text(u['full_name']?.toString() ?? ''),
                          onChanged: (checked) {
                            setState(() {
                              if (checked == true) {
                                _memberIds.add(id);
                              } else {
                                _memberIds.remove(id);
                              }
                            });
                          },
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(false),
          child: Text(t('mail_close')),
        ),
        ElevatedButton(
          onPressed: _saving ? null : _save,
          child: _saving
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Text(t('mail_group_save_btn')),
        ),
      ],
    );
  }
}
