import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:geolocator/geolocator.dart';
import 'package:dio/dio.dart';
import 'package:local_auth/local_auth.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';
import '../../features/auth/auth_provider.dart';

class HrScreen extends StatefulWidget {
  const HrScreen({super.key});

  @override
  State<HrScreen> createState() => _HrScreenState();
}

class _HrScreenState extends State<HrScreen> {
  bool _loading = true;
  bool _checking = false;
  String? _error;
  String? _offlineNotice;
  Map<String, dynamic>? _history;
  final LocalAuthentication _localAuth = LocalAuthentication();

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson('/employee/history', 'employee_history');
      if (!mounted) return;
      setState(() {
        _history = ((res.data as Map)['data'] as Map?)?.cast<String, dynamic>();
        _offlineNotice = res.fromCache ? res.errorMessage : null;
      });
    } catch (e) {
      if (!mounted) return;
      String message = AppStrings.t(
        'hr_home_load_error',
        context.read<LocaleProvider>().locale.languageCode,
      );
      if (e is DioException && e.response?.data is Map) {
        final data = e.response!.data as Map;
        message =
            data['error']?.toString() ?? data['message']?.toString() ?? message;
      }
      setState(() => _error = message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _checkAttendance(String type) async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    setState(() => _checking = true);
    try {
      final verified = await _verifyIdentity();
      if (!verified) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(t('hr_home_identity_not_verified'))),
          );
        }
        return;
      }

      Position? pos;
      try {
        var perm = await Geolocator.checkPermission();
        if (perm == LocationPermission.denied)
          perm = await Geolocator.requestPermission();
        if (perm != LocationPermission.denied &&
            perm != LocationPermission.deniedForever) {
          pos = await Geolocator.getCurrentPosition();
        }
      } catch (_) {}

      if (!mounted) return;
      final repo = context.read<CachedRepository>();
      final res = await repo.postOrQueue('/attendance/check', {
        'check_type': type,
        if (pos != null) 'latitude': pos.latitude,
        if (pos != null) 'longitude': pos.longitude,
      }, entity: 'attendance');

      if (!mounted) return;
      final msg =
          (res.data?['message'] ??
                  res.errorMessage ??
                  t('hr_home_checked_default'))
              .toString();
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
      _load();
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  Future<bool> _verifyIdentity() async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    _showVerificationDialog(t('hr_home_verifying'));
    try {
      final canCheck = await _localAuth.canCheckBiometrics;
      final isSupported = await _localAuth.isDeviceSupported();
      final biometrics = canCheck
          ? await _localAuth.getAvailableBiometrics()
          : <BiometricType>[];
      final hasStrong =
          biometrics.contains(BiometricType.strong) ||
          biometrics.contains(BiometricType.face);

      if (!isSupported || biometrics.isEmpty) {
        return await _authenticateWithDeviceCredentials();
      }

      if (hasStrong) {
        final ok = await _localAuth.authenticate(
          localizedReason: t('hr_home_biometric_reason'),
          biometricOnly: false,
          sensitiveTransaction: true,
          persistAcrossBackgrounding: true,
        );
        if (ok) return true;
        return await _authenticateWithDeviceCredentials();
      }

      return await _authenticateWithDeviceCredentials();
    } on LocalAuthException catch (e) {
      if (e.code == LocalAuthExceptionCode.noBiometricsEnrolled ||
          e.code == LocalAuthExceptionCode.noBiometricHardware ||
          e.code == LocalAuthExceptionCode.noCredentialsSet) {
        return await _authenticateWithDeviceCredentials();
      }
      if (mounted) _showAuthError(e.code);
      return false;
    } finally {
      if (mounted) Navigator.of(context).pop();
    }
  }

  Future<bool> _authenticateWithDeviceCredentials() async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final isSupported = await _localAuth.isDeviceSupported();
    if (!isSupported) {
      if (mounted) _showAuthError('noHardware');
      return false;
    }
    try {
      return await _localAuth.authenticate(
        localizedReason: t('hr_home_pin_reason'),
        biometricOnly: false,
        sensitiveTransaction: true,
        persistAcrossBackgrounding: true,
      );
    } on LocalAuthException catch (e) {
      if (mounted) _showAuthError(e.code);
      return false;
    }
  }

  void _showVerificationDialog(String message) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => AlertDialog(
        content: Row(
          children: [
            const CircularProgressIndicator(),
            const SizedBox(width: 16),
            Text(message),
          ],
        ),
      ),
    );
  }

  void _showAuthError(Object code) {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    String message;
    if (code is LocalAuthExceptionCode) {
      switch (code) {
        case LocalAuthExceptionCode.noBiometricHardware:
        case LocalAuthExceptionCode.biometricHardwareTemporarilyUnavailable:
          message = t('hr_home_auth_no_hardware');
          break;
        case LocalAuthExceptionCode.noBiometricsEnrolled:
          message = t('hr_home_auth_not_enrolled');
          break;
        case LocalAuthExceptionCode.noCredentialsSet:
          message = t('hr_home_auth_no_credentials');
          break;
        case LocalAuthExceptionCode.temporaryLockout:
        case LocalAuthExceptionCode.biometricLockout:
          message = t('hr_home_auth_lockout');
          break;
        case LocalAuthExceptionCode.userCanceled:
        case LocalAuthExceptionCode.systemCanceled:
          message = t('hr_home_auth_canceled');
          break;
        default:
          message = t('hr_home_auth_failed');
      }
    } else if (code == 'noHardware') {
      message = t('hr_home_auth_no_support');
    } else {
      message = t('hr_home_auth_failed');
    }
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final emp = _history?['employee'] as Map?;
    final attendance = (_history?['attendance'] as List?) ?? [];
    final leaves = (_history?['leaves'] as List?) ?? [];

    return RefreshIndicator(
      onRefresh: _load,
      child: Column(
        children: [
          OfflineBanner(message: _offlineNotice),
          Expanded(
            child: AsyncStateView(
              loading: _loading,
              error: _error,
              onRetry: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (emp != null)
                    Card(
                      child: ListTile(
                        title: Text(emp['name']?.toString() ?? ''),
                        subtitle: Text(
                          '${emp['position'] ?? ''} • ${emp['code'] ?? ''}',
                        ),
                      ),
                    ),
                  Row(
                    children: [
                      Expanded(
                        child: ElevatedButton.icon(
                          onPressed: _checking
                              ? null
                              : () => _checkAttendance('in'),
                          icon: _checking
                              ? const SizedBox(
                                  width: 18,
                                  height: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Colors.white,
                                  ),
                                )
                              : const Icon(Icons.login),
                          label: Text(
                            _checking
                                ? t('hr_home_checking')
                                : t('hr_home_check_in'),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _checking
                              ? null
                              : () => _checkAttendance('out'),
                          icon: const Icon(Icons.logout),
                          label: Text(t('hr_home_check_out')),
                        ),
                      ),
                    ],
                  ),
                  if (_checking)
                    const Padding(
                      padding: EdgeInsets.only(top: 12),
                      child: Center(child: CircularProgressIndicator()),
                    ),
                  const SizedBox(height: 16),
                  if (context.select(
                    (AuthProvider a) =>
                        a.can('hr.manage') ||
                        a.hasRole('موارد بشرية') ||
                        a.hasRole('hr'),
                  )) ...[
                    ElevatedButton.icon(
                      onPressed: () => context.push('/hr/exceptional-advance'),
                      icon: const Icon(Icons.request_quote_outlined),
                      label: Text(t('hr_home_advance_button')),
                    ),
                    const SizedBox(height: 12),
                  ],
                  ElevatedButton.icon(
                    onPressed: () => context.push('/hr/salary'),
                    icon: const Icon(Icons.payments_outlined),
                    label: Text(t('hr_home_salary_button')),
                  ),
                  const SizedBox(height: 12),
                  if (context.select(
                    (AuthProvider a) =>
                        a.can('mobile.evaluations') ||
                        a.can('hr.evaluation.manage'),
                  )) ...[
                    ElevatedButton.icon(
                      onPressed: () => context.push('/hr/team-evaluations'),
                      icon: const Icon(Icons.fact_check_outlined),
                      label: Text(t('hr_home_team_eval_button')),
                    ),
                    const SizedBox(height: 12),
                  ],
                  const SizedBox(height: 16),
                  Text(
                    t('hr_home_recent_attendance'),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  ...attendance.take(10).map((a) {
                    final m = a as Map;
                    return ListTile(
                      dense: true,
                      leading: Icon(
                        m['check_type'] == 'in' ? Icons.login : Icons.logout,
                        color: m['within_geofence'] == 1
                            ? Colors.green
                            : Colors.orange,
                      ),
                      title: Text(m['checked_at'].toString()),
                    );
                  }),
                  const SizedBox(height: 16),
                  Text(
                    t('hr_home_leaves'),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  ...leaves.map((l) {
                    final m = l as Map;
                    return ListTile(
                      dense: true,
                      title: Text(
                        '${m['leave_type']} — ${m['date_from']} ${t('hr_home_leave_to')} ${m['date_to']}',
                      ),
                      trailing: Text(m['status'].toString()),
                    );
                  }),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
