import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:provider/provider.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';

/// زر/أيقونة تفتح كاميرا المسح وتُرجع قيمة الباركود الأولى عبر [onCode].
class BarcodeScanButton extends StatelessWidget {
  const BarcodeScanButton({
    super.key,
    required this.onCode,
    this.icon = Icons.qr_code_scanner,
  });
  final ValueChanged<String> onCode;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    return IconButton(
      icon: Icon(icon),
      tooltip: AppStrings.t('barcode_scanner_tooltip', lang),
      onPressed: () => _scan(context),
    );
  }

  Future<void> _scan(BuildContext context) async {
    final code = await showDialog<String>(
      context: context,
      builder: (_) => const _ScannerDialog(),
    );
    if (code != null && code.isNotEmpty) onCode(code);
  }
}

class _ScannerDialog extends StatefulWidget {
  const _ScannerDialog();

  @override
  State<_ScannerDialog> createState() => _ScannerDialogState();
}

class _ScannerDialogState extends State<_ScannerDialog> {
  bool _detected = false;

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      contentPadding: EdgeInsets.zero,
      content: SizedBox(
        width: double.maxFinite,
        height: 320,
        child: MobileScanner(
          fit: BoxFit.cover,
          onDetect: (capture) {
            if (_detected) return;
            final code = capture.barcodes.isEmpty
                ? null
                : capture.barcodes.first.rawValue;
            if (code != null && code.isNotEmpty) {
              setState(() => _detected = true);
              if (mounted) Navigator.pop(context, code);
            }
          },
        ),
      ),
    );
  }
}
