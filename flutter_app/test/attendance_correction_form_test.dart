import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:top_organic_app/core/network/api_client.dart';
import 'package:top_organic_app/core/locale/locale_provider.dart';
import 'package:top_organic_app/features/hr/team_request_screen.dart';

class FakeAttendanceApi extends ApiClient {
  int submissions = 0;
  @override
  Future<Response> post(String path, {dynamic data, Options? options}) async {
    submissions++;
    return Response(
      requestOptions: RequestOptions(path: path),
      data: {'ok': true},
    );
  }
}

void main() {
  for (final platform in [TargetPlatform.android, TargetPlatform.iOS]) {
    testWidgets(
      'Correction exposes original punch and validates input on $platform',
      (tester) async {
        await tester.binding.setSurfaceSize(const Size(320, 1000));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final api = FakeAttendanceApi();
        await tester.pumpWidget(
          MultiProvider(
            providers: [
              Provider<ApiClient>.value(value: api),
              ChangeNotifierProvider(create: (_) => LocaleProvider()),
            ],
            child: MaterialApp(
              theme: ThemeData(platform: platform),
              builder: (c, w) => MediaQuery(
                data: MediaQuery.of(
                  c,
                ).copyWith(textScaler: TextScaler.linear(1.5)),
                child: Directionality(
                  textDirection: TextDirection.rtl,
                  child: w!,
                ),
              ),
              home: const TeamRequestScreen(
                employeeId: 7,
                employeeName: 'موظف تجريبي',
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(find.text('وقت الدخول الأصلي'), findsNothing);
        await tester.tap(find.byType(DropdownButtonFormField<String>).first);
        await tester.pumpAndSettle();
        await tester.tap(find.text('تصحيح بصمة خاطئة').last);
        await tester.pumpAndSettle();
        expect(find.text('وقت الدخول الأصلي'), findsOneWidget);
        expect(find.text('وقت الخروج الأصلي'), findsNothing);
        expect(tester.takeException(), isNull);
        await tester.ensureVisible(find.text('إرسال الطلب'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('إرسال الطلب'));
        await tester.pumpAndSettle();
        expect(find.byType(SnackBar), findsOneWidget);
        expect(api.submissions, 0);
        expect(tester.takeException(), isNull);
      },
    );
  }
}
