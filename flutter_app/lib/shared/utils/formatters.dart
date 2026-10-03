import 'package:intl/intl.dart';

/// أدوات تنسيق موحّدة للأرقام والعملة، متوافقة مع Helpers::money() في الخلفية
class Formatters {
  Formatters._();

  static final NumberFormat _integerFormat = NumberFormat.decimalPattern('en');

  /// ينسّق رقماً عادياً (بدون عملة) مع فواصل الآلاف، مثال: 12345 -> 12,345
  /// يقبل رقماً أو نصاً قابلاً للتحويل (لأن الـ API قد يرسل الأرقام كنص)
  static String number(dynamic value) {
    return _integerFormat.format(toDouble(value));
  }

  /// يحوّل أي قيمة (نص/رقم) إلى double بأمان
  static double toDouble(dynamic value) {
    if (value == null) return 0;
    if (value is num) return value.toDouble();
    return double.tryParse(value.toString()) ?? 0;
  }

  /// ينسّق مبلغاً بعملة الدينار العراقي، مثال: 15000 -> "15,000 IQD"
  static String currency(dynamic value) {
    final amount = toDouble(value);
    return '${_integerFormat.format(amount)} IQD';
  }
}
