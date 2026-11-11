import 'dart:async';
import 'package:dio/dio.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:top_organic_app/core/data/cached_repository.dart';
import 'package:top_organic_app/core/locale/app_strings.dart';
import 'package:top_organic_app/core/locale/locale_provider.dart';
import 'package:top_organic_app/core/network/api_client.dart';
import 'package:top_organic_app/core/storage/auth_storage.dart';
import 'package:top_organic_app/features/auth/auth_provider.dart';
import 'package:top_organic_app/features/hr/attendance_integrity.dart';
import 'package:top_organic_app/features/hr/attendance_screen.dart';
import 'package:top_organic_app/features/hr/hr_screen.dart';

class FakeRepository extends CachedRepository {
  FakeRepository() : super(ApiClient());
  int submissions = 0;
  int reports = 0;
  int queued = 0;
  bool offline = false;
  @override
  Future<ApiResult<dynamic>> getJson(
    String path,
    String cacheKey, {
    Map<String, dynamic>? query,
    int? ttlSeconds,
  }) async => ApiResult({'data': <String, dynamic>{}}, fromCache: false);
  @override
  Future<ApiResult<Map<String, dynamic>?>> postJson(
    String path,
    Map<String, dynamic> data,
  ) async {
    if (path == '/attendance/location-alert') {
      reports++;
      if (offline) throw StateError('offline');
      return ApiResult({'ok': true}, fromCache: false);
    }
    submissions++;
    if (offline) {
      throw DioException(
        requestOptions: RequestOptions(path: path),
        type: DioExceptionType.connectionError,
      );
    }
    return ApiResult({'ok': true}, fromCache: false);
  }

  @override
  Future<ApiResult<Map<String, dynamic>?>> postOrQueue(
    String path,
    Map<String, dynamic> data, {
    required String entity,
  }) async {
    queued++;
    return ApiResult(null, fromCache: true);
  }
}

Position fix(bool mocked) => Position(
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
Widget harness(Widget child, FakeRepository repo) => MultiProvider(
  providers: [
    Provider<CachedRepository>.value(value: repo),
    ChangeNotifierProvider(create: (_) => LocaleProvider()),
    ChangeNotifierProvider(
      create: (_) => AuthProvider(ApiClient(), AuthStorage()),
    ),
  ],
  child: MaterialApp(home: Scaffold(body: child)),
);
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const authChannel = MethodChannel('plugins.flutter.io/local_auth');
  setUp(
    () => TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
          authChannel,
          (call) async =>
              call.method == 'getAvailableBiometrics' ? ['face'] : true,
        ),
  );
  tearDown(
    () => TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(authChannel, null),
  );
  for (final dedicated in [true, false]) {
    for (final type in ['in', 'out']) {
      testWidgets(
        'mock $type blocked and reported from ${dedicated ? "attendance" : "HR"}',
        (tester) async {
          final repo = FakeRepository();
          var reads = 0;
          final guard = AttendanceIntegrityGuard(
            locate: () async {
              reads++;
              return fix(true);
            },
          );
          await tester.pumpWidget(
            harness(
              dedicated
                  ? AttendanceScreen(integrityGuard: guard)
                  : HrScreen(integrityGuard: guard),
              repo,
            ),
          );
          await tester.pumpAndSettle();
          final key = dedicated
              ? 'hr_attendance_check_${type}_button'
              : 'hr_home_check_$type';
          await tester.tap(find.text(AppStrings.t(key, 'ar')));
          await tester.pumpAndSettle();
          expect(
            find.text(AppStrings.t('attendance_mock_blocked', 'ar')),
            findsOneWidget,
          );
          expect(repo.submissions, 0);
          expect(repo.queued, 0);
          expect(repo.reports, 1);
          await tester.tap(find.text(AppStrings.t('retry', 'ar')));
          await tester.pumpAndSettle();
          expect(reads, 2);
          expect(repo.reports, 2);
          expect(repo.submissions, 0);
          expect(tester.takeException(), isNull);
        },
      );
    }
    testWidgets('offline audit failure does not allow a punch in $dedicated', (
      tester,
    ) async {
      final repo = FakeRepository()..offline = true;
      final guard = AttendanceIntegrityGuard(locate: () async => fix(true));
      await tester.pumpWidget(
        harness(
          dedicated
              ? AttendanceScreen(integrityGuard: guard)
              : HrScreen(integrityGuard: guard),
          repo,
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(
        find.text(
          AppStrings.t(
            dedicated ? 'hr_attendance_check_in_button' : 'hr_home_check_in',
            'ar',
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(repo.reports, 1);
      expect(repo.submissions, 0);
      expect(repo.queued, 0);
      expect(tester.takeException(), isNull);
    });
    testWidgets(
      'duplicate taps and disposal during location check are safe in $dedicated',
      (tester) async {
        final repo = FakeRepository();
        final pending = Completer<Position?>();
        var reads = 0;
        final guard = AttendanceIntegrityGuard(
          locate: () {
            reads++;
            return pending.future;
          },
        );
        await tester.pumpWidget(
          harness(
            dedicated
                ? AttendanceScreen(integrityGuard: guard)
                : HrScreen(integrityGuard: guard),
            repo,
          ),
        );
        await tester.pumpAndSettle();
        final button = find.text(
          AppStrings.t(
            dedicated ? 'hr_attendance_check_in_button' : 'hr_home_check_in',
            'ar',
          ),
        );
        await tester.tap(button);
        await tester.tap(button);
        await tester.pump();
        expect(reads, 1);
        await tester.pumpWidget(const SizedBox.shrink());
        pending.complete(fix(true));
        await tester.pumpAndSettle();
        expect(repo.submissions, 0);
        expect(tester.takeException(), isNull);
      },
    );
  }
  testWidgets('clean retry after mock rejection submits once, never queues', (
    tester,
  ) async {
    final repo = FakeRepository();
    var mocked = true;
    final guard = AttendanceIntegrityGuard(locate: () async => fix(mocked));
    await tester.pumpWidget(harness(HrScreen(integrityGuard: guard), repo));
    await tester.pumpAndSettle();
    await tester.tap(find.text(AppStrings.t('hr_home_check_in', 'ar')));
    await tester.pumpAndSettle();
    mocked = false;
    await tester.tap(find.text(AppStrings.t('retry', 'ar')));
    await tester.pumpAndSettle();
    expect(repo.reports, 1);
    expect(repo.submissions, 1);
    expect(repo.queued, 0);
  });
}
