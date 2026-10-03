import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:geolocator/geolocator.dart';
import 'package:local_auth/local_auth.dart';
import 'package:permission_handler/permission_handler.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';
import 'liveness_camera_screen.dart';

/// شاشة بصمة الحضور والانصراف native (بدون WebView)
class AttendanceScreen extends StatefulWidget {
  const AttendanceScreen({super.key});

  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen> {
  bool _loading = true;
  bool _checking = false;
  String? _error;
  String? _offlineNotice;
  Map<String, dynamic>? _status;
  File? _photo;
  final LocalAuthentication _localAuth = LocalAuthentication();

  @override
  void initState() {
    super.initState();
    _loadStatus();
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => _requestCameraOnFirstEntry(),
    );
  }

  /// طلب إذن الكاميرا فور الدخول لشاشة الحضور أول مرة حتى تظهر نافذة النظام
  /// ويظهر خيار الكاميرا لاحقاً في إعدادات التطبيق.
  Future<void> _requestCameraOnFirstEntry() async {
    try {
      final status = await Permission.camera.status;
      if (status.isDenied) await Permission.camera.request();
    } catch (_) {}
  }

  Future<void> _loadStatus() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson('/attendance/status', 'attendance_status');
      if (!mounted) return;
      setState(() {
        _status = ((res.data as Map)['data'] as Map?)?.cast<String, dynamic>();
        _offlineNotice = res.fromCache ? res.errorMessage : null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _error = AppStrings.t(
          'hr_attendance_load_error',
          context.read<LocaleProvider>().locale.languageCode,
        ),
      );
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _takeSelfie() async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    // تحقق من الحالة الحالية أولاً
    var status = await Permission.camera.status;

    if (status.isGranted) {
      // الإذن ممنوح مسبقاً — افتح الكاميرا مباشرة
      if (!mounted) return;
      final file = await Navigator.push<File>(
        context,
        MaterialPageRoute(builder: (_) => const LivenessCameraScreen()),
      );
      if (file != null && mounted) setState(() => _photo = file);
      return;
    }

    if (status.isPermanentlyDenied) {
      // المستخدم رفض الإذن بشكل دائم — وجّهه إلى إعدادات التطبيق
      if (mounted) {
        final open = await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: Text(t('hr_attendance_camera_permission_title')),
            content: Text(t('hr_attendance_camera_permission_content')),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: Text(t('cancel')),
              ),
              TextButton(
                onPressed: () => Navigator.pop(context, true),
                child: Text(t('hr_attendance_open_settings')),
              ),
            ],
          ),
        );
        if (open == true) {
          await openAppSettings();
        }
      }
      return;
    }

    // اطلب الإذن (لم يُطلب بعد أو رُفض مؤقتاً)
    status = await Permission.camera.request();
    if (!status.isGranted) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(t('hr_attendance_camera_required')),
            backgroundColor: Colors.orange,
          ),
        );
      }
      return;
    }
    if (!mounted) return;
    final file = await Navigator.push<File>(
      context,
      MaterialPageRoute(builder: (_) => const LivenessCameraScreen()),
    );
    if (file != null && mounted) setState(() => _photo = file);
  }

  Future<void> _check(String type) async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    setState(() => _checking = true);
    final repo = context.read<CachedRepository>();
    try {
      final verified = await _verifyIdentity();
      if (!verified) {
        if (mounted)
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(t('hr_attendance_identity_not_verified'))),
          );
        return;
      }

      await _takeSelfie();
      if (_photo == null) {
        if (mounted)
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(t('hr_attendance_photo_required'))),
          );
        return;
      }
      final selfieBytes = await _photo!.readAsBytes();
      final selfieBase64 = base64Encode(selfieBytes);

      final bool faceRequired = (_status?['face_recognition_required'] == true);
      final bool hasReference = (_status?['has_reference_photo'] == true);
      if (faceRequired) {
        if (!hasReference) {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(t('hr_attendance_no_reference_photo')),
                backgroundColor: Colors.orange,
              ),
            );
          }
          return;
        }
        final verifyRes = await repo.postJson('/attendance/verify-face', {
          'selfie': selfieBase64,
        });
        if (!mounted) return;
        if (verifyRes.data?['ok'] != true ||
            verifyRes.data?['matched'] != true) {
          final similarity = verifyRes.data?['similarity'];
          final msg = similarity != null
              ? '${t('hr_attendance_face_mismatch_prefix')}$similarity${t('hr_attendance_face_mismatch_suffix')}'
              : verifyRes.errorMessage ?? t('hr_attendance_face_verify_failed');
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(msg), backgroundColor: Colors.red),
          );
          return;
        }
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
      final payload = <String, dynamic>{
        'check_type': type,
        if (pos != null) 'latitude': pos.latitude,
        if (pos != null) 'longitude': pos.longitude,
        'photo': selfieBase64,
      };

      final res = await repo.postJson('/attendance/check', payload);

      if (!mounted) return;
      if (res.data?['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              res.data?['message'] ?? t('hr_attendance_checked_default'),
            ),
            backgroundColor: Colors.green,
          ),
        );
        setState(() => _photo = null);
        _loadStatus();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res.errorMessage ?? t('hr_attendance_check_failed')),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  Future<void> _uploadReferencePhoto() async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    await _takeSelfie();
    if (_photo == null) return;
    if (!mounted) return;
    setState(() => _checking = true);
    final repo = context.read<CachedRepository>();
    try {
      final bytes = await _photo!.readAsBytes();
      final res = await repo.postJson('/attendance/reference-photo', {
        'photo': base64Encode(bytes),
      });
      if (!mounted) return;
      if (res.data?['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(t('hr_attendance_reference_saved')),
            backgroundColor: Colors.green,
          ),
        );
        _loadStatus();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              res.errorMessage ?? t('hr_attendance_reference_save_failed'),
            ),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  Future<bool> _verifyIdentity() async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    try {
      final canCheck = await _localAuth.canCheckBiometrics;
      final isSupported = await _localAuth.isDeviceSupported();
      if (!isSupported) {
        return await _confirmContinueWithoutAuth(
          t('hr_attendance_device_not_supported'),
        );
      }

      final biometrics = canCheck
          ? await _localAuth.getAvailableBiometrics()
          : <BiometricType>[];
      final hasStrong =
          biometrics.contains(BiometricType.strong) ||
          biometrics.contains(BiometricType.face);

      if (hasStrong) {
        final ok = await _localAuth.authenticate(
          localizedReason: t('hr_attendance_biometric_reason'),
          biometricOnly: false,
        );
        if (ok) return true;
      }

      // fallback: PIN/Pattern/Password
      final ok = await _localAuth.authenticate(
        localizedReason: t('hr_attendance_pin_reason'),
        biometricOnly: false,
      );
      if (ok) return true;

      return await _confirmContinueWithoutAuth(
        t('hr_attendance_biometric_not_setup'),
      );
    } on LocalAuthException catch (e) {
      if (e.code == LocalAuthExceptionCode.noBiometricsEnrolled ||
          e.code == LocalAuthExceptionCode.noBiometricHardware ||
          e.code == LocalAuthExceptionCode.noCredentialsSet) {
        return await _confirmContinueWithoutAuth(
          t('hr_attendance_biometric_not_setup'),
        );
      }
      return false;
    }
  }

  Future<bool> _confirmContinueWithoutAuth(String reason) async {
    if (!mounted) return false;
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('hr_attendance_security_alert_title')),
        content: Text(
          '$reason${t('hr_attendance_continue_without_auth_suffix')}',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('cancel')),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('hr_attendance_continue')),
          ),
        ],
      ),
    );
    return ok ?? false;
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final branch = _status?['branch'] as Map?;
    final clockIn = _status?['clock_in'];
    final clockOut = _status?['clock_out'];
    final strict = _status?['strict'] == true;

    return Scaffold(
      appBar: AppBar(title: Text(t('hr_attendance_title'))),
      body: RefreshIndicator(
        onRefresh: _loadStatus,
        child: Column(
          children: [
            OfflineBanner(message: _offlineNotice),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : _error != null
                  ? Center(
                      child: Text(_error!, textDirection: TextDirection.rtl),
                    )
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        if (branch != null)
                          Card(
                            child: ListTile(
                              leading: const Icon(Icons.location_on),
                              title: Text(branch['name']?.toString() ?? ''),
                              subtitle: Text(
                                '${t('hr_attendance_allowed_radius_prefix')}${branch['allowed_radius_meters'] ?? 200}${t('hr_attendance_allowed_radius_suffix')}',
                              ),
                            ),
                          ),
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              children: [
                                Row(
                                  mainAxisAlignment:
                                      MainAxisAlignment.spaceAround,
                                  children: [
                                    _buildTimeBox(
                                      t('hr_attendance_clock_in_label'),
                                      clockIn?.toString(),
                                    ),
                                    _buildTimeBox(
                                      t('hr_attendance_clock_out_label'),
                                      clockOut?.toString(),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 20),
                                if (_photo != null)
                                  ClipRRect(
                                    borderRadius: BorderRadius.circular(12),
                                    child: Image.file(
                                      _photo!,
                                      height: 160,
                                      fit: BoxFit.cover,
                                    ),
                                  ),
                                const SizedBox(height: 20),
                                Row(
                                  children: [
                                    Expanded(
                                      child: ElevatedButton.icon(
                                        onPressed: _checking
                                            ? null
                                            : () => _check('in'),
                                        icon: _checking
                                            ? const SizedBox(
                                                width: 18,
                                                height: 18,
                                                child:
                                                    CircularProgressIndicator(
                                                      strokeWidth: 2,
                                                      color: Colors.white,
                                                    ),
                                              )
                                            : const Icon(Icons.login),
                                        label: Text(
                                          _checking
                                              ? t('hr_attendance_checking')
                                              : t(
                                                  'hr_attendance_check_in_button',
                                                ),
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: OutlinedButton.icon(
                                        onPressed: _checking
                                            ? null
                                            : () => _check('out'),
                                        icon: const Icon(Icons.logout),
                                        label: Text(
                                          t('hr_attendance_check_out_button'),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                if (strict)
                                  Padding(
                                    padding: const EdgeInsets.only(top: 12),
                                    child: Text(
                                      t('hr_attendance_geofence_notice'),
                                      style: const TextStyle(
                                        color: Colors.orange,
                                        fontSize: 12,
                                      ),
                                    ),
                                  ),
                                if (_status?['face_recognition_required'] ==
                                        true &&
                                    _status?['has_reference_photo'] != true)
                                  Padding(
                                    padding: const EdgeInsets.only(top: 12),
                                    child: ElevatedButton.icon(
                                      onPressed: _checking
                                          ? null
                                          : _uploadReferencePhoto,
                                      icon: _checking
                                          ? const SizedBox(
                                              width: 18,
                                              height: 18,
                                              child: CircularProgressIndicator(
                                                strokeWidth: 2,
                                                color: Colors.white,
                                              ),
                                            )
                                          : const Icon(Icons.face),
                                      label: Text(
                                        t('hr_attendance_upload_reference'),
                                      ),
                                    ),
                                  ),
                              ],
                            ),
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

  Widget _buildTimeBox(String label, String? value) {
    return Column(
      children: [
        Text(label, style: const TextStyle(fontWeight: FontWeight.bold)),
        Text(
          value ?? '—',
          style: const TextStyle(fontSize: 16, color: Colors.grey),
        ),
      ],
    );
  }
}
