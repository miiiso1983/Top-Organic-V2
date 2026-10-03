import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:provider/provider.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../auth/auth_provider.dart';

/// الملف الشخصي: تغيير كلمة المرور وتبديل لغة التطبيق
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  final _currentController = TextEditingController();
  final _newController = TextEditingController();
  final _confirmController = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _currentController.dispose();
    _newController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _submit(String lang) async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);
    try {
      final api = context.read<ApiClient>();
      final res = await api.post(
        '/change-password',
        data: {
          'current_password': _currentController.text,
          'new_password': _newController.text,
          'new_password_confirmation': _confirmController.text,
        },
      );
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      if (body['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(AppStrings.t('password_changed_ok', lang))),
        );
        _currentController.clear();
        _newController.clear();
        _confirmController.clear();
        _formKey.currentState!.reset();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(body['error']?.toString() ?? '')),
        );
      }
    } on DioException catch (e) {
      if (!mounted) return;
      final data = e.response?.data;
      final message = (data is Map ? data['error']?.toString() : null) ?? '';
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final localeProvider = context.watch<LocaleProvider>();
    final lang = localeProvider.locale.languageCode;
    final auth = context.watch<AuthProvider>();
    String t(String key) => AppStrings.t(key, lang);

    return Directionality(
      textDirection: localeProvider.textDirection,
      child: Scaffold(
        appBar: AppBar(title: Text(t('profile_title'))),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: ListTile(
                title: Text(auth.user?['name']?.toString() ?? ''),
                subtitle: Text(
                  [
                    auth.user?['job_title']?.toString() ?? '',
                    auth.user?['department']?.toString() ?? '',
                  ].where((s) => s.isNotEmpty).join(' • '),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    const Icon(Icons.language_outlined),
                    const SizedBox(width: 12),
                    Expanded(child: Text(t('language'))),
                    SegmentedButton<String>(
                      segments: [
                        ButtonSegment(
                          value: 'ar',
                          label: Text(t('language_arabic')),
                        ),
                        ButtonSegment(
                          value: 'en',
                          label: Text(t('language_english')),
                        ),
                      ],
                      selected: {lang},
                      onSelectionChanged: (_) => localeProvider.toggle(),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            Text(
              t('change_password'),
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 8),
            Form(
              key: _formKey,
              child: Column(
                children: [
                  TextFormField(
                    controller: _currentController,
                    obscureText: true,
                    decoration: InputDecoration(
                      labelText: t('current_password'),
                    ),
                    validator: (v) =>
                        (v == null || v.isEmpty) ? t('required_field') : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _newController,
                    obscureText: true,
                    decoration: InputDecoration(labelText: t('new_password')),
                    validator: (v) {
                      if (v == null || v.isEmpty) return t('required_field');
                      if (v.length < 6) return t('password_min_length');
                      return null;
                    },
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _confirmController,
                    obscureText: true,
                    decoration: InputDecoration(
                      labelText: t('confirm_password'),
                    ),
                    validator: (v) {
                      if (v == null || v.isEmpty) return t('required_field');
                      if (v != _newController.text)
                        return t('password_mismatch');
                      return null;
                    },
                  ),
                  const SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: _submitting ? null : () => _submit(lang),
                      icon: _submitting
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Icon(Icons.lock_reset_outlined),
                      label: Text(t('save')),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
