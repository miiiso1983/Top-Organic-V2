import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'config.dart';

/// هوية Top Organic البصرية: أخضر عضوي + خط Cairo
class AppTheme {
  AppTheme._();

  static const Color brandDark = Color(AppConfig.brandDarkValue);
  static const Color brand = Color(AppConfig.brandValue);
  static const Color sidebar = Color(AppConfig.sidebarValue);
  static const Color sidebarHover = Color(AppConfig.sidebarHoverValue);

  static ThemeData light() {
    final base = ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(
        seedColor: sidebar,
        primary: sidebar,
        secondary: brandDark,
        brightness: Brightness.light,
      ),
      scaffoldBackgroundColor: const Color(0xFFF2F4F7),
    );

    final textTheme = GoogleFonts.cairoTextTheme(base.textTheme);

    return base.copyWith(
      textTheme: textTheme,
      appBarTheme: AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF1F2937),
        elevation: 0.5,
        titleTextStyle: GoogleFonts.cairo(
          fontSize: 18,
          fontWeight: FontWeight.w700,
          color: const Color(0xFF1F2937),
        ),
      ),
      navigationRailTheme: NavigationRailThemeData(
        backgroundColor: sidebar,
        selectedIconTheme: const IconThemeData(color: Colors.white),
        unselectedIconTheme: const IconThemeData(color: Colors.white70),
        selectedLabelTextStyle: GoogleFonts.cairo(
          color: Colors.white,
          fontWeight: FontWeight.bold,
        ),
        unselectedLabelTextStyle: GoogleFonts.cairo(color: Colors.white70),
        indicatorColor: sidebarHover,
      ),
      drawerTheme: const DrawerThemeData(backgroundColor: sidebar),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: brand,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
        ),
      ),
      cardTheme: CardThemeData(
        elevation: 1,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        margin: const EdgeInsets.symmetric(vertical: 6),
      ),
      inputDecorationTheme: InputDecorationTheme(
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
        filled: true,
        fillColor: Colors.white,
      ),
    );
  }
}
