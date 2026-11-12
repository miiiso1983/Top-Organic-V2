import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';
import '../network/api_client.dart';
import 'app_release.dart';
import 'update_policy.dart';

class UpdateGate extends StatefulWidget {
  const UpdateGate({
    super.key,
    required this.api,
    required this.child,
    this.release,
    this.openStore,
  });
  final ApiClient api;
  final Widget child;
  final AppRelease? release;
  final Future<bool> Function(Uri)? openStore;
  @override
  State<UpdateGate> createState() => _UpdateGateState();
}

class _UpdateGateState extends State<UpdateGate> with WidgetsBindingObserver {
  AppRelease? _release;
  UpdatePolicy? _policy;
  bool _starting = true, _checking = false, _opening = false;
  String? _error;
  Timer? _timer;
  int _revision = 0;
  String get _cacheKey => 'mandatory_update_policy_${_release!.platform}';
  bool get _blocked =>
      _release != null &&
      (_policy?.blocks(_release!.platform, _release!.build) ?? false);
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    UpdateRequiredSignal.policy.addListener(_onRequired);
    _start();
    _timer = Timer.periodic(const Duration(minutes: 2), (_) => _refresh());
  }

  Future<void> _start() async {
    try {
      _release = widget.release ?? await AppRelease.current();
      final prefs = await SharedPreferences.getInstance();
      final cached = prefs.getString(_cacheKey);
      if (cached != null && _policy == null)
        _policy = UpdatePolicy.parse(jsonDecode(cached));
      final signal = UpdateRequiredSignal.policy.value;
      if (signal?.platform == _release!.platform) _policy = signal;
      await _refresh();
    } catch (_) {
      /* An unavailable package/cache must not crash startup. API checks still apply. */
    }
    if (mounted) setState(() => _starting = false);
  }

  void _onRequired() {
    final p = UpdateRequiredSignal.policy.value;
    if (p == null || (_release != null && p.platform != _release!.platform))
      return;
    _revision++;
    if (mounted) setState(() => _policy = p);
    _persist(p);
  }

  Future<void> _persist(UpdatePolicy policy) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(
        'mandatory_update_policy_${policy.platform}',
        jsonEncode(policy.toJson()),
      );
    } catch (_) {}
  }

  Future<void> _refresh() async {
    if (_checking ||
        _release == null ||
        !['android', 'ios'].contains(_release!.platform))
      return;
    _checking = true;
    final revision = _revision;
    if (mounted) setState(() => _error = null);
    try {
      final response = await widget.api
          .get('/app/update-policy', query: {'platform': _release!.platform})
          .timeout(const Duration(seconds: 8));
      final p = UpdatePolicy.parse(
        response.data is Map ? response.data['data'] : null,
      );
      if (p == null || p.platform != _release!.platform)
        throw const FormatException();
      // A newer 426 takes precedence over an already-running policy request.
      if (revision == _revision) {
        _policy = p;
        await _persist(p);
      }
    } catch (_) {
      _error = 'تعذر التحقق من التحديث. تحقق من الاتصال وأعد المحاولة.';
    } finally {
      _checking = false;
      if (mounted) setState(() {});
    }
  }

  Future<void> _open() async {
    if (_opening || _policy == null) return;
    setState(() => _opening = true);
    try {
      final ok =
          await (widget.openStore?.call(_policy!.store) ??
              launchUrl(_policy!.store, mode: LaunchMode.externalApplication));
      if (!ok) throw StateError('store');
    } catch (_) {
      if (mounted)
        setState(
          () => _error =
              'تعذر فتح المتجر. افتح المتجر وابحث عن Top Organic ثم حدّث التطبيق.',
        );
    } finally {
      if (mounted) setState(() => _opening = false);
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _refresh();
  }

  @override
  void dispose() {
    _timer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    UpdateRequiredSignal.policy.removeListener(_onRequired);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Replacing the routed content prevents back/deep-link navigation behind the update page.
    if (_starting && !_blocked)
      return const Material(child: Center(child: CircularProgressIndicator()));
    if (!_blocked) return widget.child;
    final english = Localizations.localeOf(context).languageCode == 'en';
    return PopScope(
      canPop: false,
      child: Scaffold(
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(28),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 460),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.system_update_rounded,
                      size: 72,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                    const SizedBox(height: 24),
                    Text(
                      english ? 'Update required' : 'تحديث التطبيق مطلوب',
                      style: Theme.of(context).textTheme.headlineSmall,
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      english
                          ? 'Update Top Organic from the store to continue using the app securely.'
                          : 'يرجى تحديث تطبيق Top Organic من المتجر للاستمرار واستخدام خدمات الموظفين بأمان.',
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      '${english ? 'Required version' : 'الإصدار المطلوب'}: ${_policy!.version}',
                    ),
                    const SizedBox(height: 24),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton.icon(
                        onPressed: _opening ? null : _open,
                        icon: const Icon(Icons.open_in_new),
                        label: Text(english ? 'Update now' : 'تحديث الآن'),
                      ),
                    ),
                    TextButton(
                      onPressed: _checking ? null : _refresh,
                      child: Text(english ? 'Check again' : 'إعادة التحقق'),
                    ),
                    if (_error != null)
                      Text(
                        english
                            ? 'Could not complete the request. Check your connection or open the store and search for Top Organic.'
                            : _error!,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
