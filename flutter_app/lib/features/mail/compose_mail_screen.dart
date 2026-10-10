import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import 'mail_groups_screen.dart';

/// نموذج إنشاء رسالة بريد داخلية جديدة (أو رد على رسالة واردة) — يدعم
/// اختيار مستلمين أفراد و/أو أقسام كاملة و/أو مجموعات بريد، بالإضافة لإرفاق صور.
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
  bool _loadingDepartments = true;
  bool _loadingGroups = true;
  bool _sending = false;
  String? _usersError;
  String? _departmentsError;
  String? _groupsError;
  List<dynamic> _users = [];
  List<dynamic> _departments = [];
  List<dynamic> _groups = [];
  final Set<int> _selectedIds = {};
  final Map<int, String> _selectedNames = {};
  final Set<int> _selectedDepartmentIds = {};
  final Set<int> _selectedGroupIds = {};
  final List<XFile> _attachments = [];

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
    _loadDepartments();
    _loadGroups();
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

  Future<void> _loadDepartments() async {
    setState(() {
      _loadingDepartments = true;
      _departmentsError = null;
    });
    try {
      final api = context.read<ApiClient>();
      final res = await api.get('/mail/departments');
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      setState(() => _departments = (body['data'] as List?) ?? []);
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _departmentsError = AppStrings.t(
          'mail_departments_load_error',
          context.read<LocaleProvider>().locale.languageCode,
        ),
      );
    } finally {
      if (mounted) setState(() => _loadingDepartments = false);
    }
  }

  Future<void> _loadGroups() async {
    setState(() {
      _loadingGroups = true;
      _groupsError = null;
    });
    try {
      final api = context.read<ApiClient>();
      final res = await api.get('/mail/groups');
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      setState(() => _groups = (body['data'] as List?) ?? []);
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _groupsError = AppStrings.t(
          'mail_groups_load_error',
          context.read<LocaleProvider>().locale.languageCode,
        ),
      );
    } finally {
      if (mounted) setState(() => _loadingGroups = false);
    }
  }

  Future<void> _openGroupsManager() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const MailGroupsScreen()),
    );
    _loadGroups();
  }

  Future<void> _pickAttachments() async {
    try {
      final picker = ImagePicker();
      final files = await picker.pickMultiImage(imageQuality: 80);
      if (files.isEmpty) return;
      setState(() => _attachments.addAll(files));
    } catch (_) {}
  }

  Future<void> _send(String Function(String) t) async {
    final subject = _subjectController.text.trim();
    final body = _bodyController.text.trim();
    if (_selectedIds.isEmpty && _selectedDepartmentIds.isEmpty && _selectedGroupIds.isEmpty) {
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
      final formData = FormData();
      formData.fields
        ..add(MapEntry('subject', subject))
        ..add(MapEntry('body', body));
      for (final id in _selectedIds) {
        formData.fields.add(MapEntry('recipient_ids[]', id.toString()));
      }
      for (final id in _selectedDepartmentIds) {
        formData.fields.add(MapEntry('department_ids[]', id.toString()));
      }
      for (final id in _selectedGroupIds) {
        formData.fields.add(MapEntry('group_ids[]', id.toString()));
      }
      for (final file in _attachments) {
        formData.files.add(
          MapEntry(
            'attachments[]',
            await MultipartFile.fromFile(file.path, filename: file.name),
          ),
        );
      }
      final res = await api.post('/mail/compose', data: formData);
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
      String message = t('mail_send_error');
      if (e is DioException && e.response?.data is Map) {
        message = (e.response!.data as Map)['error']?.toString() ?? message;
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
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
            Text(
              t('mail_recipients_optional_note'),
              style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
            ),
            const SizedBox(height: 12),

            // === المستلمون الأفراد ===
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
              height: 160,
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

            // === الأقسام ===
            Text(t('mail_departments_label'), style: const TextStyle(fontWeight: FontWeight.bold)),
            Text(
              t('mail_departments_hint'),
              style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
            ),
            const SizedBox(height: 6),
            _loadingDepartments
                ? const Padding(
                    padding: EdgeInsets.all(8),
                    child: Center(child: CircularProgressIndicator()),
                  )
                : _departmentsError != null
                    ? Text(_departmentsError!)
                    : Wrap(
                        spacing: 6,
                        runSpacing: 6,
                        children: _departments.cast<Map>().map((d) {
                          final id = d['id'] as int;
                          final selected = _selectedDepartmentIds.contains(id);
                          return FilterChip(
                            label: Text(d['name']?.toString() ?? ''),
                            selected: selected,
                            onSelected: (v) {
                              setState(() {
                                if (v) {
                                  _selectedDepartmentIds.add(id);
                                } else {
                                  _selectedDepartmentIds.remove(id);
                                }
                              });
                            },
                          );
                        }).toList(),
                      ),

            const Divider(height: 32),

            // === مجموعات البريد ===
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(t('mail_groups_label'), style: const TextStyle(fontWeight: FontWeight.bold)),
                TextButton.icon(
                  onPressed: _openGroupsManager,
                  icon: const Icon(Icons.settings, size: 16),
                  label: Text(t('mail_groups_manage_btn')),
                ),
              ],
            ),
            const SizedBox(height: 6),
            _loadingGroups
                ? const Padding(
                    padding: EdgeInsets.all(8),
                    child: Center(child: CircularProgressIndicator()),
                  )
                : _groupsError != null
                    ? Text(_groupsError!)
                    : Wrap(
                        spacing: 6,
                        runSpacing: 6,
                        children: _groups.cast<Map>().map((g) {
                          final id = g['id'] as int;
                          final selected = _selectedGroupIds.contains(id);
                          return FilterChip(
                            label: Text(
                              '${g['name'] ?? ''} (${g['members_count'] ?? 0})',
                            ),
                            selected: selected,
                            onSelected: (v) {
                              setState(() {
                                if (v) {
                                  _selectedGroupIds.add(id);
                                } else {
                                  _selectedGroupIds.remove(id);
                                }
                              });
                            },
                          );
                        }).toList(),
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
            const SizedBox(height: 16),

            // === المرفقات ===
            Text(t('mail_attachments_label'), style: const TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 6),
            if (_attachments.isNotEmpty)
              SizedBox(
                height: 80,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: _attachments.length,
                  separatorBuilder: (_, _) => const SizedBox(width: 8),
                  itemBuilder: (context, i) => Stack(
                    clipBehavior: Clip.none,
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: Image.file(
                          File(_attachments[i].path),
                          width: 80,
                          height: 80,
                          fit: BoxFit.cover,
                        ),
                      ),
                      Positioned(
                        top: -6,
                        left: -6,
                        child: GestureDetector(
                          onTap: () => setState(() => _attachments.removeAt(i)),
                          child: const CircleAvatar(
                            radius: 10,
                            backgroundColor: Colors.red,
                            child: Icon(Icons.close, size: 14, color: Colors.white),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: _pickAttachments,
              icon: const Icon(Icons.attach_file),
              label: Text(t('mail_attach_btn')),
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
