import 'package:flutter/material.dart';

/// Asia/Baghdad currently uses UTC+03:00 with no daylight saving time.
/// UTC DateTimes here carry wall-clock components, not instants, so the device
/// timezone cannot change comparisons or the naive timestamps sent to the API.
DateTime attendanceNow({DateTime? instant}) =>
    (instant ?? DateTime.now()).toUtc().add(const Duration(hours: 3));

DateTime attendanceWallTime(DateTime value) => DateTime.utc(
  value.year,
  value.month,
  value.day,
  value.hour,
  value.minute,
  value.second,
  value.millisecond,
  value.microsecond,
);

bool isFutureAttendanceTime(DateTime value, {DateTime? instant}) =>
    attendanceWallTime(value).isAfter(attendanceNow(instant: instant));

DateTime attendanceInitialDate(DateTime? initial, DateTime now) {
  final value = attendanceWallTime(initial ?? now);
  if (value.isBefore(DateTime.utc(2000))) return DateTime.utc(2000);
  return value.isAfter(now) ? now : value;
}

/// Original punches are references and may themselves be erroneous/future.
Future<DateTime?> pickAttendanceTime(
  BuildContext context, {
  DateTime? initial,
  bool restrictToPast = true,
  required String futureMessage,
  DateTime Function()? clock,
}) async {
  DateTime now() => attendanceNow(instant: clock?.call());
  final current = now();
  final selected = restrictToPast
      ? attendanceInitialDate(initial, current)
      : (initial ?? current);
  final date = await showDatePicker(
    context: context,
    initialDate: selected,
    firstDate: DateTime(2000),
    lastDate: restrictToPast ? current : DateTime(2100),
    currentDate: current,
  );
  if (date == null || !context.mounted) return null;
  final time = await showTimePicker(
    context: context,
    initialTime: TimeOfDay.fromDateTime(selected),
  );
  if (time == null || !context.mounted) return null;
  final result = DateTime.utc(
    date.year,
    date.month,
    date.day,
    time.hour,
    time.minute,
  );
  if (restrictToPast && result.isAfter(now())) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(futureMessage)));
    return null;
  }
  return result;
}
