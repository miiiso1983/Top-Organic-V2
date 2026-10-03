import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';

class OvertimeRequestScreen extends StatefulWidget {
  const OvertimeRequestScreen({super.key});

  @override
  State<OvertimeRequestScreen> createState() => _OvertimeRequestScreenState();
}

class _OvertimeRequestScreenState extends State<OvertimeRequestScreen> {
  bool _submitting = false;
  final _formKey = GlobalKey<FormState>();
  final _dateController = TextEditingController();
  final _hoursController = TextEditingController();
  final _reasonController = TextEditingController();

  Future<void> _submit() async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);
    try {
      final api = context.read<ApiClient>();
      await api.post(
        '/overtime',
        data: {
          'request_date': _dateController.text,
          'hours': double.tryParse(_hoursController.text) ?? 0,
          'reason': _reasonController.text,
        },
      );
      if (!mounted) return;
      _dateController.clear();
      _hoursController.clear();
      _reasonController.clear();
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('hr_overtime_request_sent'))));
      Navigator.of(context).maybePop();
    } catch (e) {
      if (!mounted) return;
      String message = t('hr_overtime_request_send_error');
      if (e is DioException && e.response?.data is Map) {
        message = (e.response!.data as Map)['error']?.toString() ?? message;
      } else if (e is Exception && e.toString().isNotEmpty) {
        message = e.toString();
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final yesterday = now.subtract(const Duration(days: 1));
    final picked = await showDatePicker(
      context: context,
      initialDate: now,
      // لا يُسمح بتاريخ يسبق يوم الأمس (سياسة العمل: أمس، اليوم، أو المستقبل فقط)
      firstDate: DateTime(yesterday.year, yesterday.month, yesterday.day),
      lastDate: DateTime(now.year + 1),
    );
    if (picked != null) {
      _dateController.text =
          '${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(title: Text(t('hr_overtime_request_title'))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Form(
            key: _formKey,
            child: Column(
              children: [
                TextFormField(
                  controller: _dateController,
                  decoration: InputDecoration(
                    labelText: t('hr_overtime_request_date_label'),
                    suffixIcon: const Icon(Icons.calendar_today),
                  ),
                  readOnly: true,
                  onTap: _pickDate,
                  validator: (v) =>
                      v == null || v.isEmpty ? t('required_field') : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _hoursController,
                  decoration: InputDecoration(
                    labelText: t('hr_overtime_request_hours_label'),
                  ),
                  keyboardType: TextInputType.number,
                  validator: (v) {
                    if (v == null || v.isEmpty) return t('required_field');
                    if ((double.tryParse(v) ?? 0) <= 0)
                      return t('hr_overtime_request_invalid_value');
                    return null;
                  },
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _reasonController,
                  decoration: InputDecoration(
                    labelText: t('hr_overtime_request_reason_label'),
                  ),
                  maxLines: 2,
                ),
                const SizedBox(height: 16),
                ElevatedButton.icon(
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
                        ? t('hr_overtime_request_submitting')
                        : t('hr_overtime_request_submit_button'),
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
