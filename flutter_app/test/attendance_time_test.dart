import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:top_organic_app/core/utils/attendance_time.dart';

void main() {
  final instant = DateTime.utc(2026, 11, 6, 21, 30, 20);
  test('Baghdad date is independent of the device timezone', () {
    expect(
      attendanceNow(instant: instant),
      DateTime.utc(2026, 11, 7, 0, 30, 20),
    );
    expect(
      attendanceNow(instant: instant.toLocal()),
      attendanceNow(instant: instant),
    );
  });
  test('current minute, elapsed midnight exit and prior day are allowed', () {
    for (final value in [
      DateTime(2026, 11, 7, 0, 30),
      DateTime(2026, 11, 7, 0, 10),
      DateTime(2026, 11, 6, 23, 59),
    ]) {
      expect(isFutureAttendanceTime(value, instant: instant), isFalse);
    }
  });
  test('same-day future minute and future day are blocked', () {
    expect(
      isFutureAttendanceTime(DateTime(2026, 11, 7, 0, 31), instant: instant),
      isTrue,
    );
    expect(
      isFutureAttendanceTime(DateTime(2026, 11, 8), instant: instant),
      isTrue,
    );
  });
  test('stale future initial selection is clamped safely', () {
    final now = attendanceNow(instant: instant);
    expect(attendanceInitialDate(DateTime(2027), now), now);
    expect(attendanceInitialDate(DateTime(1990), now), DateTime.utc(2000));
  });

  testWidgets('future minute is rejected without replacing saved time', (
    tester,
  ) async {
    DateTime? saved = DateTime.utc(2026, 11, 6, 20);
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Builder(
            builder: (context) {
              return TextButton(
                onPressed: () async {
                  final result = await pickAttendanceTime(
                    context,
                    initial: DateTime.utc(2026, 11, 7, 0, 30),
                    futureMessage: 'Future punch',
                    clock: () => instant,
                  );
                  if (result != null) saved = result;
                },
                child: const Text('Pick'),
              );
            },
          ),
        ),
      ),
    );
    await tester.tap(find.text('Pick'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
    await tester.tap(find.byIcon(Icons.keyboard_outlined));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField).last, '31');
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
    expect(find.text('Future punch'), findsOneWidget);
    expect(saved, DateTime.utc(2026, 11, 6, 20));
    expect(tester.takeException(), isNull);
  });

  for (final restrictToPast in [true, false]) {
    testWidgets(
      'picker cancellation, repetition and original-reference distinction: $restrictToPast',
      (tester) async {
        DateTime? saved = DateTime.utc(2026, 11, 6, 20);
        await tester.pumpWidget(
          MaterialApp(
            home: Scaffold(
              body: Builder(
                builder: (context) {
                  return TextButton(
                    onPressed: () async {
                      final result = await pickAttendanceTime(
                        context,
                        initial: DateTime.utc(2026, 11, 8, 12),
                        restrictToPast: restrictToPast,
                        futureMessage: 'Future punch',
                        clock: () => instant,
                      );
                      if (result != null) saved = result;
                    },
                    child: const Text('Pick'),
                  );
                },
              ),
            ),
          ),
        );
        await tester.tap(find.text('Pick'));
        await tester.pumpAndSettle();
        final picker = tester.widget<DatePickerDialog>(
          find.byType(DatePickerDialog),
        );
        expect(picker.lastDate.year, restrictToPast ? 2026 : 2100);
        expect(picker.initialDate!.day, restrictToPast ? 7 : 8);
        await tester.tap(find.text('Cancel'));
        await tester.pumpAndSettle();
        expect(saved, DateTime.utc(2026, 11, 6, 20));
        await tester.tap(find.text('Pick'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('OK'));
        await tester.pumpAndSettle();
        expect(find.byType(TimePickerDialog), findsOneWidget);
        await tester.tap(find.text('Cancel'));
        await tester.pumpAndSettle();
        expect(saved, DateTime.utc(2026, 11, 6, 20));
        await tester.tap(find.text('Pick'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('OK'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('OK'));
        await tester.pumpAndSettle();
        expect(
          saved,
          restrictToPast
              ? DateTime.utc(2026, 11, 7, 0, 30)
              : DateTime.utc(2026, 11, 8, 12),
        );
        expect(tester.takeException(), isNull);
      },
    );
  }
}
