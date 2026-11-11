import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';
import 'package:top_organic_app/features/hr/attendance_integrity.dart';

Position fix({bool mocked = false}) => Position(
  longitude: 44.4,
  latitude: 33.3,
  timestamp: DateTime.now(),
  accuracy: 10,
  altitude: 0,
  altitudeAccuracy: 0,
  heading: 0,
  headingAccuracy: 0,
  speed: 0,
  speedAccuracy: 0,
  isMocked: mocked,
);
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  for (final platform in [TargetPlatform.android, TargetPlatform.iOS]) {
    test('mock rejection and fresh retry on $platform', () async {
      debugDefaultTargetPlatformOverride = platform;
      addTearDown(() => debugDefaultTargetPlatformOverride = null);
      var mocked = true;
      final guard = AttendanceIntegrityGuard(
        locate: () async => fix(mocked: mocked),
      );
      await expectLater(
        guard.capture(),
        throwsA(isA<AttendanceIntegrityException>()),
      );
      mocked = false;
      expect((await guard.capture()).payload, {
        'latitude': 33.3,
        'longitude': 44.4,
        'attendance_integrity': {'mock_location': false},
      });
    });
  }
  test('VPN is not queried or used as a reason to refuse attendance', () async {
    var calls = 0;
    const channel = MethodChannel('dev.fluttercommunity.plus/connectivity');
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async {
          calls++;
          return ['vpn'];
        });
    addTearDown(
      () => TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(channel, null),
    );
    final result = await AttendanceIntegrityGuard(
      locate: () async => fix(),
    ).capture();
    expect(result.position, isNotNull);
    expect(calls, 0);
    expect(
      (result.payload['attendance_integrity'] as Map).containsKey('vpn_active'),
      false,
    );
  });
  test(
    'missing GPS is not labelled as mock; geofence decision stays on server',
    () async {
      expect(
        (await AttendanceIntegrityGuard(
          locate: () async => null,
        ).capture()).payload,
        {'attendance_integrity': <String, dynamic>{}},
      );
    },
  );
}
