import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../../core/config.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../core/storage/auth_storage.dart';

/// شاشة WebView مخصصة لفتح صفحات الويب داخل التطبيق مع تسجيل الدخول تلقائياً
/// باستخدام API token.
class WebViewScreen extends StatefulWidget {
  final String path;
  final String title;

  const WebViewScreen({super.key, required this.path, required this.title});

  @override
  State<WebViewScreen> createState() => _WebViewScreenState();
}

class _WebViewScreenState extends State<WebViewScreen> {
  WebViewController? _controller;
  bool _loading = true;
  String? _error;

  /// ترجمة نص حسب اللغة الحالية، قابلة للاستخدام خارج build() أيضاً
  String t(String key) =>
      AppStrings.t(key, context.read<LocaleProvider>().locale.languageCode);

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    final token = await AuthStorage().getToken();
    if (token == null || token.isEmpty) {
      setState(() {
        _error = t('webview_not_logged_in');
        _loading = false;
      });
      return;
    }

    final url =
        '${AppConfig.baseUrl}${widget.path}?mobile_token=${Uri.encodeComponent(token)}';

    final controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (_) => setState(() => _loading = true),
          onPageFinished: (_) => setState(() => _loading = false),
          onWebResourceError: (err) {
            setState(() {
              _error = err.description.isNotEmpty
                  ? err.description
                  : t('webview_load_failed');
              _loading = false;
            });
          },
        ),
      )
      ..loadRequest(Uri.parse(url));

    setState(() {
      _controller = controller;
      _loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _controller == null ? null : () => _controller!.reload(),
          ),
        ],
      ),
      body: _error != null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Text(
                  _error!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.red),
                ),
              ),
            )
          : _controller == null
          ? const Center(child: CircularProgressIndicator())
          : Stack(
              children: [
                WebViewWidget(controller: _controller!),
                if (_loading) const Center(child: CircularProgressIndicator()),
              ],
            ),
    );
  }
}
