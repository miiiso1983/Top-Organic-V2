import '../../core/data/cached_repository.dart';
import 'package:geolocator/geolocator.dart';

/// A best-effort, on-demand check, not device attestation or proof of presence.
/// No polling, app scanning, extra permissions or external service is used.
class AttendanceIntegrityException implements Exception {
  const AttendanceIntegrityException(this.messageKey);
  final String messageKey;
}

class AttendanceLocation {
  const AttendanceLocation(this.position);
  final Position? position;
  Map<String, dynamic> get payload => {
    if (position != null) 'latitude': position!.latitude,
    if (position != null) 'longitude': position!.longitude,
    'attendance_integrity': {
      if (position != null) 'mock_location': position!.isMocked,
    },
  };
}

class AttendanceIntegrityGuard {
  AttendanceIntegrityGuard({Future<Position?> Function()? locate})
    : _locate = locate ?? _currentPosition;
  final Future<Position?> Function() _locate;

  Future<AttendanceLocation> capture() async {
    final position = await _locate();
    if (position?.isMocked == true) {
      throw const AttendanceIntegrityException('attendance_mock_blocked');
    }
    return AttendanceLocation(position);
  }

  /// Best effort: a reporting failure never permits a rejected punch or queues it.
  static Future<void> reportMock(
    CachedRepository repository,
    String type,
  ) async {
    try {
      await repository
          .postJson('/attendance/location-alert', {
            'check_type': type,
            'mock_location': true,
          })
          .timeout(const Duration(seconds: 3));
    } catch (_) {
      /* Offline detection remains blocked locally. */
    }
  }

  static Future<Position?> _currentPosition() async {
    try {
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        return null;
      }
      return await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 12),
        ),
      );
    } catch (_) {
      // Preserve existing server-side geofence and authorized exemption rules.
      // Missing GPS is not evidence of spoofing and must not be labelled as it.
      return null;
    }
  }
}
