import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

class PrivacyPolicyButton extends StatelessWidget {
  const PrivacyPolicyButton({super.key, this.color, this.launch});
  final Color? color;
  final Future<bool> Function(Uri)? launch;
  static final uri = Uri.parse(
    'https://www.toporganic.rest/privacy.html?v=20261112',
  );
  @override
  Widget build(BuildContext context) {
    final ar = Localizations.localeOf(context).languageCode == 'ar';
    return TextButton.icon(
      icon: const Icon(Icons.privacy_tip_outlined),
      label: Text(ar ? 'سياسة الخصوصية' : 'Privacy policy'),
      style: TextButton.styleFrom(foregroundColor: color),
      onPressed: () async {
        try {
          final ok =
              await (launch?.call(uri) ??
                  launchUrl(uri, mode: LaunchMode.externalApplication));
          if (ok) return;
        } catch (_) {}
        if (!context.mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              ar
                  ? 'تعذر فتح المتصفح. يمكنك قراءة السياسة على www.toporganic.rest/privacy.html'
                  : 'Could not open the browser. Read the policy at www.toporganic.rest/privacy.html',
            ),
          ),
        );
      },
    );
  }
}
