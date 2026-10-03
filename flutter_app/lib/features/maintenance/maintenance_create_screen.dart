import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../auth/auth_provider.dart';

class MaintenanceCreateScreen extends StatefulWidget {
  const MaintenanceCreateScreen({super.key});

  @override
  State<MaintenanceCreateScreen> createState() =>
      _MaintenanceCreateScreenState();
}

class _MaintenanceCreateScreenState extends State<MaintenanceCreateScreen> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _deviceController = TextEditingController();
  final _descriptionController = TextEditingController();
  File? _photo;
  bool _submitting = false;
  String _priority = 'normal';

  /// ترجمة نص حسب اللغة الحالية، قابلة للاستخدام خارج build() أيضاً
  String t(String key) =>
      AppStrings.t(key, context.read<LocaleProvider>().locale.languageCode);

  Map<String, String> _priorityLabels() => {
    'low': t('maintenance_create_priority_low'),
    'normal': t('maintenance_create_priority_normal'),
    'high': t('maintenance_create_priority_high'),
    'urgent': t('maintenance_create_priority_urgent'),
  };

  Future<void> _pickImage(ImageSource source) async {
    final picker = ImagePicker();
    final file = await picker.pickImage(source: source, imageQuality: 80);
    if (!mounted) return;
    if (file != null) setState(() => _photo = File(file.path));
  }

  Future<void> _chooseImageSource() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt),
              title: Text(t('maintenance_create_capture_from_camera')),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: Text(t('maintenance_create_pick_from_gallery')),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source != null) await _pickImage(source);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_photo == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('maintenance_create_photo_required'))),
      );
      return;
    }
    setState(() => _submitting = true);
    final api = context.read<ApiClient>();
    try {
      final bytes = await _photo!.readAsBytes();
      final ext = _photo!.path.contains('.')
          ? _photo!.path.split('.').last.toLowerCase()
          : 'jpg';
      final mime = ext == 'png' ? 'image/png' : 'image/jpeg';
      final base64 = 'data:$mime;base64,${base64Encode(bytes)}';
      final res = await api.post(
        '/maintenance',
        data: {
          'title': _titleController.text.trim(),
          'device_name': _deviceController.text.trim(),
          'description': _descriptionController.text.trim(),
          'priority': _priority,
          'image_base64': base64,
        },
      );
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      if (body['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${t('maintenance_create_submitted_prefix')}${body['request_number'] ?? body['id']}',
            ),
          ),
        );
        context.go('/maintenance');
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              body['error']?.toString() ??
                  t('maintenance_create_submit_failed'),
            ),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      String message = t('maintenance_create_submit_error');
      if (e is Exception) {
        final msg = e.toString();
        if (msg.contains('error')) message = msg;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;
    return Scaffold(
      appBar: AppBar(title: Text(t('maintenance_create_title'))),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: ListView(
            children: [
              TextFormField(
                initialValue: user?['name']?.toString() ?? '—',
                enabled: false,
                decoration: InputDecoration(
                  labelText: t('maintenance_create_requester_name_label'),
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                initialValue: user?['job_title']?.toString() ?? '—',
                enabled: false,
                decoration: InputDecoration(
                  labelText: t('maintenance_create_job_title_label'),
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                initialValue: user?['department']?.toString() ?? '—',
                enabled: false,
                decoration: InputDecoration(
                  labelText: t('maintenance_create_department_label'),
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _titleController,
                decoration: InputDecoration(
                  labelText: t('maintenance_create_fault_name_label'),
                ),
                validator: (v) => v == null || v.trim().isEmpty
                    ? t('maintenance_create_required_field')
                    : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _deviceController,
                decoration: InputDecoration(
                  labelText: t('maintenance_create_device_name_label'),
                ),
                validator: (v) => v == null || v.trim().isEmpty
                    ? t('maintenance_create_required_field')
                    : null,
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _priority,
                decoration: InputDecoration(
                  labelText: t('maintenance_create_priority_label'),
                ),
                items: _priorityLabels().entries
                    .map(
                      (e) =>
                          DropdownMenuItem(value: e.key, child: Text(e.value)),
                    )
                    .toList(),
                onChanged: (v) => setState(() => _priority = v ?? 'normal'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _descriptionController,
                decoration: InputDecoration(
                  labelText: t('maintenance_create_additional_notes_label'),
                ),
                maxLines: 3,
              ),
              const SizedBox(height: 20),
              Text(
                t('maintenance_create_fault_photo_label'),
                style: Theme.of(context).textTheme.titleSmall,
              ),
              const SizedBox(height: 8),
              if (_photo != null)
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Image.file(_photo!, height: 200, fit: BoxFit.cover),
                ),
              const SizedBox(height: 8),
              ElevatedButton.icon(
                onPressed: _chooseImageSource,
                icon: const Icon(Icons.add_a_photo),
                label: Text(
                  _photo == null
                      ? t('maintenance_create_attach_photo_button')
                      : t('maintenance_create_change_photo_button'),
                ),
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: _submitting ? null : _submit,
                  icon: _submitting
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.send),
                  label: Text(
                    _submitting
                        ? t('maintenance_create_sending')
                        : t('maintenance_create_submit_button'),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
