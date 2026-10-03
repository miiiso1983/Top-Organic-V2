import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// يدير اللغة الحالية للتطبيق (عربي/إنجليزي) ويحفظها محلياً بين الجلسات.
/// يُستخدم حالياً في الشاشات المدعومة بالترجمة (الملف الشخصي، السكن) وسيتوسّع تدريجياً.
class LocaleProvider extends ChangeNotifier {
  static const _prefsKey = 'app_locale';

  Locale _locale = const Locale('ar');
  Locale get locale => _locale;
  bool get isArabic => _locale.languageCode == 'ar';
  TextDirection get textDirection =>
      isArabic ? TextDirection.rtl : TextDirection.ltr;

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    final code = prefs.getString(_prefsKey);
    if (code == 'en') {
      _locale = const Locale('en');
      notifyListeners();
    }
  }

  Future<void> toggle() async {
    _locale = isArabic ? const Locale('en') : const Locale('ar');
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_prefsKey, _locale.languageCode);
  }
}
