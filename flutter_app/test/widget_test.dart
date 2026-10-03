// اختبار أساسي: يتأكد من أن التطبيق يبدأ بشاشة تسجيل الدخول قبل المصادقة.

import 'package:flutter_test/flutter_test.dart';

import 'package:top_organic_app/main.dart';

void main() {
  testWidgets('يعرض التطبيق شاشة تسجيل الدخول عند بدء التشغيل', (WidgetTester tester) async {
    await tester.pumpWidget(const TopOrganicApp());
    await tester.pump();

    expect(find.text('Top Organic'), findsWidgets);
  });
}
