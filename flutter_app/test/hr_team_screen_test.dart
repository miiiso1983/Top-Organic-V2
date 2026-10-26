import 'dart:async';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:top_organic_app/core/network/api_client.dart';
import 'package:top_organic_app/features/hr/hr_team_screen.dart';

class TeamFake extends ApiClient {
  final calls = <Map<String, dynamic>>[];
  bool self = false;
  @override
  Future<Response> get(String path, {Map<String, dynamic>? query}) async {
    calls.add({'path': path, ...?query});
    dynamic data;
    if (path.endsWith('/employees')) {
      data = {
        'rows': [
          {
            'id': 1,
            'full_name': 'موظف تجريبي ذو اسم طويل لاختبار الإجراءات والصلاحيات',
            'emp_code': 'TEST',
            'department': 'الصالة',
            'job_title': 'موظف صالة',
            'actions': self
                ? {'archive': 'أرشيف الموظف', 'payslip': 'كشف الراتب'}
                : {
                    'archive': 'أرشيف الموظف',
                    'notice': 'تنبيه وتوجيه',
                    'warning': 'طلب إنذار',
                    'transfer': 'نقل إلى مدير آخر',
                    'photo_loading': 'تحميلات مصورة',
                  },
          },
        ],
        'total': 60,
        'page_size': query?['page_size'] ?? 25,
      };
    } else if (path.contains('/form/')) {
      data = {
        'title': 'تنبيه وتوجيه',
        'fields': [
          {
            'name': 'subject',
            'label': 'العنوان',
            'required': true,
            'type': 'text',
          },
          {
            'name': 'body',
            'label': 'التفاصيل',
            'required': true,
            'type': 'textarea',
          },
        ],
      };
    } else {
      data = {'rows': [], 'total': 0, 'page_size': 25};
    }
    return Response(
      requestOptions: RequestOptions(path: path),
      data: {'ok': true, 'data': data},
    );
  }

  @override
  Future<Response> post(String path, {dynamic data, Options? options}) async {
    calls.add({'path': path, 'data': data});
    return Response(
      requestOptions: RequestOptions(path: path),
      data: {
        'ok': true,
        'data': {'case_id': 5},
      },
    );
  }
}

Widget host(
  Widget child,
  TeamFake api,
  TargetPlatform platform, {
  double scale = 1,
}) => Provider<ApiClient>.value(
  value: api,
  child: MaterialApp(
    theme: ThemeData(platform: platform, fontFamily: 'Amiri'),
    builder: (context, c) => MediaQuery(
      data: MediaQuery.of(
        context,
      ).copyWith(textScaler: TextScaler.linear(scale)),
      child: c!,
    ),
    home: child,
  ),
);
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(() async {
    await (FontLoader(
      'Amiri',
    )..addFont(rootBundle.load('assets/fonts/Amiri-Regular.ttf'))).load();
  });
  for (final platform in [TargetPlatform.android, TargetPlatform.iOS]) {
    for (final width in [320.0, 390.0]) {
      testWidgets(
        'Scoped team actions fit $platform width $width at large text',
        (tester) async {
          await tester.binding.setSurfaceSize(Size(width, 1000));
          addTearDown(() => tester.binding.setSurfaceSize(null));
          final api = TeamFake();
          await tester.pumpWidget(
            host(const HrTeamScreen(), api, platform, scale: 1.8),
          );
          await tester.pumpAndSettle();
          expect(find.text('طلب إنذار'), findsOneWidget);
          expect(find.text('كشف الراتب'), findsNothing);
          expect(tester.takeException(), isNull);
          await tester.tap(find.byIcon(Icons.chevron_left));
          await tester.pumpAndSettle();
          expect(api.calls.last['page'], 2);
          expect(tester.takeException(), isNull);
        },
      );
    }
    testWidgets('Self service never renders manager actions on $platform', (
      tester,
    ) async {
      final api = TeamFake()..self = true;
      await tester.pumpWidget(host(const HrTeamScreen(), api, platform));
      await tester.pumpAndSettle();
      expect(find.text('كشف الراتب'), findsOneWidget);
      expect(find.text('طلب إنذار'), findsNothing);
      expect(find.text('نقل إلى مدير آخر'), findsNothing);
      expect(tester.takeException(), isNull);
    });
    testWidgets(
      'New form validates before submit and includes retry key on $platform',
      (tester) async {
        final api = TeamFake();
        await tester.pumpWidget(
          host(
            const HrTeamFormScreen(
              employee: {'id': 1, 'full_name': 'موظف تجريبي'},
              action: 'notice',
            ),
            api,
            platform,
          ),
        );
        await tester.pumpAndSettle();
        await tester.tap(find.text('إرسال'));
        await tester.pumpAndSettle();
        expect(api.calls.where((c) => c.containsKey('data')), isEmpty);
        await tester.enterText(find.byType(TextFormField).at(0), 'عنوان');
        await tester.enterText(find.byType(TextFormField).at(1), 'تعليمات');
        await tester.tap(find.text('إرسال'));
        await tester.pumpAndSettle();
        final sent = api.calls.last;
        expect(sent['data']['subject'], 'عنوان');
        expect((sent['data']['request_key'] as String).length, 48);
        expect(sent['path'], '/hr-team/employees/1/action/notice');
        expect(tester.takeException(), isNull);
      },
    );
  }
}
