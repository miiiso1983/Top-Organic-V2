/// إعدادات عامة للتطبيق
class AppConfig {
  AppConfig._();

  /// رابط الخادم الأساسي (Cloudways) - يمكن تغييره لاحقاً بسهولة
  static const String baseUrl = 'https://www.toporganic.rest';

  static const String apiBaseUrl = '$baseUrl/api';

  static const String appName = 'Top Organic';

  // ألوان الهوية البصرية
  static const int brandDarkValue = 0xFF1B5E20;
  static const int brandValue = 0xFF2E7D32;
  static const int sidebarValue = 0xFF62BB46;
  static const int sidebarHoverValue = 0xFF10914D;

  /// عرض الشاشة الذي يعتبر بعده الجهاز تابلت/سطح مكتب
  static const double tabletBreakpoint = 900;

  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 20);
}
