import 'dart:io';
import 'package:pdf/pdf.dart';
import 'package:top_organic_app/features/approvals/advance_receipt.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:top_organic_app/features/approvals/advance_policy.dart';
import 'package:top_organic_app/features/approvals/advance_snapshot_card.dart';
import 'package:top_organic_app/core/locale/locale_provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  test('Arabic paid receipt generates a printable PDF', () async {
    final bytes = await buildAdvanceReceipt({
      'id': 123,
      'full_name': 'موظف تجريبي',
      'emp_code': 'TEST-123',
      'amount': '180000.00',
      'paid_at': '2026-10-26 11:30:00',
      'payment_method': 'cash',
      'paid_by_name': 'المحاسب التجريبي',
      'reason': 'سلفة شهرية للاختبار',
    }, PdfPageFormat.a4);
    expect(String.fromCharCodes(bytes.take(4)), '%PDF');
    expect(
      String.fromCharCodes(bytes.skip(bytes.length - 30)),
      contains('%%EOF'),
    );
    if (Platform.environment['ADVANCE_RECEIPT_TEST_OUTPUT']
        case final String path)
      await File(path).writeAsBytes(bytes);
  });
  final snapshot = <String, dynamic>{
    'monthly_open': true,
    'remaining': 120000,
    'salary': 1000000,
    'salary_cap': 250000,
    'accrued_earnings': 600000,
    'deductions': 80000,
    'outstanding_debt': 400000,
    'net_entitlement': 120000,
    'as_of_date': '2026-10-26',
    'previous_advances': [],
  };
  test(
    'monthly and exceptional respect net entitlement; long-term is independent',
    () {
      expect(
        AdvancePolicy.validateAmount('120000', 'hr_advance', snapshot),
        isNull,
      );
      expect(
        AdvancePolicy.validateAmount('120001', 'hr_advance', snapshot),
        isNotNull,
      );
      expect(
        AdvancePolicy.validateAmount(
          '120001',
          'hr_exceptional_advance',
          snapshot,
        ),
        isNotNull,
      );
      expect(
        AdvancePolicy.validateAmount('600000', 'hr_long_advance', snapshot),
        isNull,
      );
    },
  );
  test('only monthly waits for day 13; missing/stale entitlement blocks', () {
    final early = {...snapshot, 'monthly_open': false};
    expect(AdvancePolicy.validateAmount('100', 'hr_advance', early), isNotNull);
    expect(
      AdvancePolicy.validateAmount('100', 'hr_exceptional_advance', early),
      isNull,
    );
    expect(
      AdvancePolicy.validateAmount('100', 'hr_long_advance', null),
      isNotNull,
    );
    for (final invalid in ['-1', '0', 'NaN', 'Infinity', '1.001']) {
      expect(
        AdvancePolicy.validateAmount(invalid, 'hr_long_advance', snapshot),
        isNotNull,
      );
    }
  });
  for (final platform in [TargetPlatform.android, TargetPlatform.iOS]) {
    testWidgets('financial breakdown displays on $platform', (tester) async {
      await tester.pumpWidget(
        ChangeNotifierProvider(
          create: (_) => LocaleProvider(),
          child: MaterialApp(
            theme: ThemeData(platform: platform),
            home: Scaffold(
              body: SingleChildScrollView(
                child: AdvanceSnapshotCard(
                  snapshot: snapshot,
                  code: 'hr_advance',
                ),
              ),
            ),
          ),
        ),
      );
      expect(find.text('كامل المديونية المتبقية'), findsOneWidget);
      expect(find.text('400000.00'), findsOneWidget);
      expect(find.text('المتاح بعد الطلبات القائمة'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }
}
