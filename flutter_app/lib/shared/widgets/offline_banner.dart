import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';

/// شريط تنبيه يظهر عند عرض بيانات من التخزين المؤقت (أوف لاين)
class OfflineBanner extends StatelessWidget {
  const OfflineBanner({super.key, this.message});
  final String? message;

  @override
  Widget build(BuildContext context) {
    if (message == null) return const SizedBox.shrink();
    return Container(
      width: double.infinity,
      color: const Color(0xFFFEF3C7),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          const Icon(Icons.cloud_off, size: 18, color: Color(0xFF92400E)),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message!,
              style: const TextStyle(color: Color(0xFF92400E), fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }
}

/// حالة تحميل/خطأ/فارغة موحّدة للشاشات
class AsyncStateView extends StatelessWidget {
  const AsyncStateView({
    super.key,
    required this.loading,
    this.error,
    required this.child,
    this.onRetry,
  });
  final bool loading;
  final String? error;
  final Widget child;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());
    if (error != null) {
      final lang = context.watch<LocaleProvider>().locale.languageCode;
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, color: Colors.red, size: 40),
            const SizedBox(height: 8),
            Text(error!, textAlign: TextAlign.center),
            if (onRetry != null)
              TextButton(
                onPressed: onRetry,
                child: Text(AppStrings.t('retry', lang)),
              ),
          ],
        ),
      );
    }
    return child;
  }
}
