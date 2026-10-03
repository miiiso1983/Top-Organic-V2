/// مساعد وقت بغداد.
///
/// كل التواريخ التي تُعرض للمستخدم أو تُرسل للخادم تستخدم Asia/Baghdad (UTC+3).
/// العراق حالياً لا يطبق التوقيت الصيفي، لذا يكفي التحويل من UTC بإضافة 3 ساعات.
class BaghdadTime {
  BaghdadTime._();

  static const int _offsetHours = 3;
  static const Duration _offset = Duration(hours: _offsetHours);

  /// يعيد توقيت بغداد الحالي كـ DateTime.
  static DateTime now() => DateTime.now().toUtc().add(_offset);

  /// يعيد تاريخ اليوم في بغداد بصيغة YYYY-MM-DD.
  static String today() => formatDate(now());

  /// يعيد التاريخ والوقت الحالي في بغداد بصيغة YYYY-MM-DD HH:MM.
  static String nowDateTime() => formatDateTime(now());

  /// يحول أي DateTime إلى مكونات توقيت بغداد ويعيد YYYY-MM-DD.
  static String formatDate(DateTime dt) => _datePart(_toBaghdad(dt));

  /// يحول أي DateTime إلى مكونات توقيت بغداد ويعيد HH:MM.
  static String formatTime(DateTime dt) => _timePart(_toBaghdad(dt));

  /// يحول أي DateTime إلى مكونات توقيت بغداد ويعيد YYYY-MM-DD HH:MM.
  static String formatDateTime(DateTime dt) {
    final b = _toBaghdad(dt);
    return '${_datePart(b)} ${_timePart(b)}';
  }

  static String _datePart(DateTime b) =>
      '${b.year}-${b.month.toString().padLeft(2, '0')}-${b.day.toString().padLeft(2, '0')}';

  static String _timePart(DateTime b) =>
      '${b.hour.toString().padLeft(2, '0')}:${b.minute.toString().padLeft(2, '0')}';

  static DateTime _toBaghdad(DateTime dt) => dt.toUtc().add(_offset);
}
