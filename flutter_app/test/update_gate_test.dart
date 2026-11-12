import 'dart:async';
import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:top_organic_app/core/network/api_client.dart';
import 'package:top_organic_app/core/updates/app_release.dart';
import 'package:top_organic_app/core/updates/update_gate.dart';
import 'package:top_organic_app/core/updates/update_policy.dart';

class PolicyApi extends ApiClient {
  dynamic policy;
  bool offline = false;
  Completer<Response>? pending;
  @override
  Future<Response> get(String path, {Map<String, dynamic>? query}) async {
    if (offline) throw StateError('offline');
    if (pending != null) return pending!.future;
    return Response(
      requestOptions: RequestOptions(path: path),
      data: {'data': policy},
    );
  }
}

Map<String, dynamic> p({
  String platform = 'android',
  int min = 68,
  bool enabled = true,
}) => {
  'platform': platform,
  'minimum_build': min,
  'enabled': enabled,
  'version': '1.3.8',
};
Widget harness(
  PolicyApi api, {
  String platform = 'android',
  int build = 67,
  Future<bool> Function(Uri)? open,
}) => MaterialApp(
  home: UpdateGate(
    api: api,
    release: AppRelease(platform, build, '1.3.7'),
    openStore: open,
    child: const Scaffold(body: Text('employee home')),
  ),
);
void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    UpdateRequiredSignal.policy.value = null;
  });
  testWidgets('old build blocks navigation and opens only official store', (
    t,
  ) async {
    final api = PolicyApi()..policy = p();
    Uri? opened;
    await t.pumpWidget(
      harness(
        api,
        open: (u) async {
          opened = u;
          return true;
        },
      ),
    );
    await t.pumpAndSettle();
    expect(find.text('employee home'), findsNothing);
    expect(find.text('Update required'), findsOneWidget);
    await t.tap(find.text('Update now'));
    await t.pumpAndSettle();
    expect(opened!.host, 'play.google.com');
    await t.binding.handlePopRoute();
    await t.pumpAndSettle();
    expect(find.text('employee home'), findsNothing);
    await t.pumpWidget(const SizedBox());
  });
  testWidgets('current build and iOS independent policy remain usable', (
    t,
  ) async {
    final api = PolicyApi()..policy = p();
    await t.pumpWidget(harness(api, build: 68));
    await t.pumpAndSettle();
    expect(find.text('employee home'), findsOneWidget);
    await t.pumpWidget(const SizedBox());
    api.policy = p(platform: 'ios', enabled: false);
    await t.pumpWidget(harness(api, platform: 'ios'));
    await t.pumpAndSettle();
    expect(find.text('employee home'), findsOneWidget);
    await t.pumpWidget(const SizedBox());
  });
  testWidgets(
    'cached mandatory policy survives offline, then rollback unlocks',
    (t) async {
      SharedPreferences.setMockInitialValues({
        'mandatory_update_policy_android': jsonEncode(p()),
      });
      final api = PolicyApi()..offline = true;
      await t.pumpWidget(harness(api));
      await t.pumpAndSettle();
      expect(find.text('employee home'), findsNothing);
      api.offline = false;
      api.policy = p(enabled: false);
      await t.tap(find.text('Check again'));
      await t.pumpAndSettle();
      expect(find.text('employee home'), findsOneWidget);
      await t.pumpWidget(const SizedBox());
    },
  );
  testWidgets(
    '426 during use gates immediately and preserves enforced policy on outage',
    (t) async {
      final api = PolicyApi()..policy = p(enabled: false);
      await t.pumpWidget(harness(api));
      await t.pumpAndSettle();
      UpdateRequiredSignal.accept(p());
      await t.pumpAndSettle();
      expect(find.text('employee home'), findsNothing);
      api.offline = true;
      await t.tap(find.text('Check again'));
      await t.pumpAndSettle();
      expect(find.text('Update required'), findsOneWidget);
      await t.pumpWidget(const SizedBox());
    },
  );
  testWidgets('startup offline without policy does not invent a block', (
    t,
  ) async {
    final api = PolicyApi()..offline = true;
    await t.pumpWidget(harness(api));
    await t.pumpAndSettle();
    expect(find.text('employee home'), findsOneWidget);
    await t.pumpWidget(const SizedBox());
  });
  testWidgets('store failure stays blocked with actionable message', (t) async {
    final api = PolicyApi()..policy = p();
    await t.pumpWidget(harness(api, open: (_) async => false));
    await t.pumpAndSettle();
    await t.tap(find.text('Update now'));
    await t.pumpAndSettle();
    expect(find.textContaining('Could not complete'), findsOneWidget);
    expect(find.text('employee home'), findsNothing);
    await t.pumpWidget(const SizedBox());
  });
}
