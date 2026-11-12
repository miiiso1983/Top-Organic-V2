import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:top_organic_app/core/widgets/privacy_policy_button.dart';

void main() {
  testWidgets('policy opens public official HTTPS URL without sign-in', (
    t,
  ) async {
    Uri? opened;
    await t.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: PrivacyPolicyButton(
            launch: (u) async {
              opened = u;
              return true;
            },
          ),
        ),
      ),
    );
    await t.tap(find.text('Privacy policy'));
    await t.pumpAndSettle();
    expect(opened!.scheme, 'https');
    expect(opened!.host, 'www.toporganic.rest');
    expect(opened!.path, '/privacy.html');
    expect(opened!.userInfo, isEmpty);
  });
  testWidgets('failed launch gives readable fallback instead of crashing', (
    t,
  ) async {
    await t.pumpWidget(
      MaterialApp(
        home: Scaffold(body: PrivacyPolicyButton(launch: (_) async => false)),
      ),
    );
    await t.tap(find.text('Privacy policy'));
    await t.pumpAndSettle();
    expect(
      find.textContaining('www.toporganic.rest/privacy.html'),
      findsOneWidget,
    );
  });
}
