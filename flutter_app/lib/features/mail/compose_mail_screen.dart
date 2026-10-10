import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';

/// نموذج إنشاء رسالة بريد داخلية جديدة (أو رد على رسالة واردة)
class ComposeMailScreen extends StatefulWidget {
  const ComposeMailScreen({
    super.key,
    this.initialRecipientId,
    this.initialRecipientName,
    this.initialSubject,
  });

  final int? initialRecipientId;
  final String? initialRecipientName;
  final String? initialSubject;

  @override
  State<ComposeMailScreen> createState() => _ComposeMailScreenState();
}

class _ComposeMailScreenState extends State<ComposeMailScreen> {
  final _subjectController = TextEditingController();
  final _bodyController = TextEditingController();
  final _searchController = TextEditingController();
  bool _loadingUsers = true;
  bool _sending = false;
  String? _usersError;
  List<dynamic> _users = [];
  final Set<int> _selectedIds = {};
  final Map<int, String> _selectedNames = {};

  @override
  void initState() {
    super.initState();
    if (widget.initialSubject != null) _subjectController.text = widget.initialSubject!;
    if (widget.initialRecipientId != null) {
      _selectedIds.add(widget.initialRecipientId!);
      if (widget.initialRecipientName != null) {
        _selectedNames[widget.initialRecipientId!] = widget.initialRecipientName!;
      }
    }
    _loadUsers();
  }

  @override
  void dispose() {
    _subjectController.dispose();
    _bodyController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadUsers() async {
    setState(() {
      _loadingUsers = true;
      _usersError = null;
    });
    try {
      final api = context.read<ApiClient>();
      final res = await api.get('/mail/users');
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      setState(() {
        _users = (body['data'] as List?) ?? [];
        for (final u in _users.cast<Map>()) {
          final id = u['id'] as int?;
          if (id != null) _selectedNames[id] = u['full_name']?.toString() ?? '';
        }
      });
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _usersError = AppStrings.t(
          'mail_users_load_error',
          context.read<LocaleProvider>().locale.languageCode,
        ),
      );
    } finally {
      if (mounted) setState(() => _loadingUsers = false);
    }
  }

  Future<void> _send(String Function(String) t) async {
    final subject = _subjectController.text.trim();
    final body = _bodyController.text.trim();
    if (_selectedIds.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('mail_recipients_required'))),
      );
      return;
    }
    if (subject.isEmpty || body.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('mail_subject_body_required'))),
      );
      return;
    }
    setState(() => _sending = true);
    try {
      final api = context.read<ApiClient>();
      final res = await api.post(
        '/mail/compose',
        data: {
          'subject': subject,
          'body': body,
          'recipient_ids': _selectedIds.toList(),
        },
      );
      final ok = (res.data as Map?)?['ok'] == true;
      if (!mounted) return;
      if (ok) {
        Navigator.of(context).pop(true);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text((res.data as Map?)?['error']?.toString() ?? t('mail_send_error'))),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('mail_send_error'))),
      );
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final query = _searchController.text.trim().toLowerCase();
    final filteredUsers = _users.cast<Map>().where((u) {
      if (query.isEmpty) return true;
      return (u['full_name']?.toString().toLowerCase() ?? '').contains(query);
    }).toList();

    return Scaffold(
      appBar: AppBar(
        title: Text(t('mail_compose_title')),
        actions: [
          IconButton(
            icon: _sending
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                  )
                : const Icon(Icons.send),
            onPressed: _sending ? null : () => _send(t),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(t('mail_recipients_label'), style: const TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 6),
            if (_selectedIds.isNotEmpty)
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: _selectedIds
                    .map(
                      (id) => Chip(
                        label: Text(_selectedNames[id] ?? '#$id'),
                        onDeleted: () => setState(() => _selectedIds.remove(id)),
                      ),
                    )
                    .toList(),
              ),
            const SizedBox(height: 8),
            TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: t('mail_recipients_hint'),
                prefixIcon: const Icon(Icons.search),
                border: const OutlineInputBorder(),
                isDense: true,
              ),
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 4),
            SizedBox(
              height: 180,
              child: _loadingUsers
                  ? const Center(child: CircularProgressIndicator())
                  : _usersError != null
                      ? Center(child: Text(_usersError!))
                      : ListView.builder(
                          itemCount: filteredUsers.length,
                          itemBuilder: (context, i) {
                            final u = filteredUsers[i];
                            final id = u['id'] as int;
                            return CheckboxListTile(
                              dense: true,
                              value: _selectedIds.contains(id),
                              title: Text(u['full_name']?.toString() ?? ''),
                              onChanged: (checked) {
                                setState(() {
                                  if (checked == true) {
                                    _selectedIds.add(id);
                                  } else {
                                    _selectedIds.remove(id);
                                  }
                                });
                              },
                            );
                          },
                        ),
            ),
            const Divider(height: 32),
            Text(t('mail_subject_label'), style: const TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 6),
            TextField(
              controller: _subjectController,
              decoration: InputDecoration(
                hintText: t('mail_subject_hint'),
                border: const OutlineInputBorder(),
                isDense: true,
              ),
            ),
            const SizedBox(height: 16),
            Text(t('mail_body_label'), style: const TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 6),
            TextField(
              controller: _bodyController,
              decoration: InputDecoration(
                hintText: t('mail_body_hint'),
                border: const OutlineInputBorder(),
              ),
              maxLines: 8,
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: _sending ? null : () => _send(t),
                icon: const Icon(Icons.send),
                label: Text(t('mail_send_btn')),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
