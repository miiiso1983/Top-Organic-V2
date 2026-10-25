import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:top_organic_app/core/network/api_client.dart';
import 'package:top_organic_app/core/locale/locale_provider.dart';
import 'package:top_organic_app/features/hr/team_evaluation_screen.dart';

const longName = 'موظف تجريبي ذو اسم طويل لاختبار عرض التقييم';

class TeamApi extends ApiClient {
  final pending = <Completer<Response>>[];
  final periods = <String>[];
  bool deferred = false;
  @override
  Future<Response> get(String path, {Map<String, dynamic>? query}) async {
    periods.add(query!['period'] as String);
    if (deferred) {
      final c = Completer<Response>();
      pending.add(c);
      return c.future;
    }
    return response();
  }

  Response response({String name = longName}) => Response(
    requestOptions: RequestOptions(path: '/team/evaluations'),
    data: {
      'data': [
        {
          'id': 1,
          'full_name': name,
          'job_title': 'موظف صيانة',
          'department': 'الصيانة',
          'evaluation_id': 1,
          'total_score': '73.75',
        },
        {
          'id': 2,
          'full_name': 'موظف تجريبي بانتظار التقييم',
          'job_title': 'موظف صالة',
          'department': 'الصالة',
          'evaluation_id': null,
        },
      ],
    },
  );
}

Widget screen(
  TeamApi api, {
  double scale = 1,
  TargetPlatform platform = TargetPlatform.android,
  GlobalKey? capture,
}) => MultiProvider(
  providers: [
    Provider<ApiClient>.value(value: api),
    ChangeNotifierProvider(create: (_) => LocaleProvider()),
  ],
  child: MaterialApp(
    theme: ThemeData(platform: platform, fontFamily: 'Amiri'),
    builder: (context, child) => MediaQuery(
      data: MediaQuery.of(
        context,
      ).copyWith(textScaler: TextScaler.linear(scale)),
      child: Directionality(textDirection: TextDirection.rtl, child: child!),
    ),
    home: RepaintBoundary(key: capture, child: const TeamEvaluationScreen()),
  ),
);

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(() async {
    final font = FontLoader('Amiri')
      ..addFont(rootBundle.load('assets/fonts/Amiri-Regular.ttf'));
    await font.load();
    final icons = FontLoader('MaterialIcons')
      ..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'));
    await icons.load();
  });
  for (final platform in [TargetPlatform.android, TargetPlatform.iOS]) {
    for (final width in [320.0, 390.0, 768.0]) {
      for (final scale in [1.0, 1.8]) {
        testWidgets(
          'Names and actions fit $platform width=$width scale=$scale',
          (tester) async {
            await tester.binding.setSurfaceSize(Size(width, 1100));
            addTearDown(() => tester.binding.setSurfaceSize(null));
            await tester.pumpWidget(
              screen(TeamApi(), scale: scale, platform: platform),
            );
            await tester.pumpAndSettle();
            expect(find.text('hr_eval_view_button'), findsNothing);
            expect(find.text('عرض التقييم'), findsOneWidget);
            expect(find.text('تم التقييم (73.75)'), findsOneWidget);
            expect(find.text('بانتظار التقييم'), findsOneWidget);
            expect(
              tester.getSize(find.text(longName)).width,
              greaterThan(width * .70),
            );
            expect(tester.getSize(find.text(longName)).height, lessThan(180));
            expect(tester.takeException(), isNull);
            await tester.tap(find.text(longName));
            await tester.pumpAndSettle();
            expect(find.text('عرض التقييم'), findsNWidgets(2));
            expect(tester.takeException(), isNull);
          },
        );
      }
    }
  }
  testWidgets('Late month responses cannot replace the selected month', (
    tester,
  ) async {
    final api = TeamApi()..deferred = true;
    await tester.pumpWidget(screen(api));
    await tester.tap(find.byIcon(Icons.chevron_left));
    await tester.pump();
    expect(api.pending.length, 2);
    api.pending[1].complete(api.response(name: 'الشهر الجديد'));
    await tester.pumpAndSettle();
    api.pending[0].complete(api.response(name: 'الشهر القديم'));
    await tester.pumpAndSettle();
    expect(find.text('الشهر الجديد'), findsOneWidget);
    expect(find.text('الشهر القديم'), findsNothing);
    expect(find.text(api.periods.last), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets('Leaving while loading is safe', (tester) async {
    final api = TeamApi()..deferred = true;
    await tester.pumpWidget(screen(api));
    await tester.pumpWidget(const SizedBox());
    api.pending.single.complete(api.response());
    await tester.pump();
    expect(tester.takeException(), isNull);
  });
  testWidgets('Capture synthetic fixed layout', (tester) async {
    await tester.binding.setSurfaceSize(const Size(390, 780));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final capture = GlobalKey();
    await tester.pumpWidget(screen(TeamApi(), capture: capture));
    await tester.pumpAndSettle();
    if (Platform.environment['EVALUATION_PREVIEW_OUTPUT']
        case final String path) {
      final boundary =
          capture.currentContext!.findRenderObject() as RenderRepaintBoundary;
      await tester.runAsync(() async {
        final image = await boundary.toImage(pixelRatio: 2);
        final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
        await File(path).writeAsBytes(bytes!.buffer.asUint8List());
        image.dispose();
      });
    }
  });
}
