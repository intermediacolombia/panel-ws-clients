import 'package:flutter/material.dart';

class AppTheme {
  // Colores base (WhatsApp-style)
  static const Color primary      = Color(0xFF128C7E);
  static const Color primaryLight = Color(0xFF25D366);
  static const Color textMuted    = Color(0xFF667781);

  // Burbujas (modo claro)
  static const Color outBubble = Color(0xFFDCF8C6);
  static const Color inBubble  = Colors.white;
  static const Color bgChat    = Color(0xFFECE5DD);

  // Burbujas (modo oscuro)
  static const Color outBubbleDark = Color(0xFF005C4B);
  static const Color inBubbleDark  = Color(0xFF1F2C34);
  static const Color bgChatDark    = Color(0xFF0A1929);

  static ThemeData get light => ThemeData(
    brightness: Brightness.light,
    colorScheme: ColorScheme.fromSeed(
      seedColor: primary,
      primary: primary,
      brightness: Brightness.light,
    ),
    appBarTheme: const AppBarTheme(
      backgroundColor: primary,
      foregroundColor: Colors.white,
      elevation: 0,
      titleTextStyle: TextStyle(
        color: Colors.white,
        fontSize: 18,
        fontWeight: FontWeight.w600,
      ),
      iconTheme: IconThemeData(color: Colors.white),
    ),
    scaffoldBackgroundColor: Color(0xFFF0F2F5),
    cardTheme: CardThemeData(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.all(Radius.circular(16))),
    ),
    inputDecorationTheme: InputDecorationTheme(
      border: OutlineInputBorder(borderRadius: BorderRadius.all(Radius.circular(24))),
      contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 12),
    ),
    useMaterial3: true,
  );

  static ThemeData get dark => ThemeData(
    brightness: Brightness.dark,
    colorScheme: ColorScheme.fromSeed(
      seedColor: primary,
      primary: primaryLight,
      brightness: Brightness.dark,
    ),
    appBarTheme: const AppBarTheme(
      backgroundColor: Color(0xFF1F2C34),
      foregroundColor: Colors.white,
      elevation: 0,
      titleTextStyle: TextStyle(
        color: Colors.white,
        fontSize: 18,
        fontWeight: FontWeight.w600,
      ),
      iconTheme: IconThemeData(color: Colors.white),
    ),
    scaffoldBackgroundColor: Color(0xFF0A1929),
    cardTheme: CardThemeData(
      color: Color(0xFF1F2C34),
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.all(Radius.circular(16))),
    ),
    inputDecorationTheme: InputDecorationTheme(
      border: OutlineInputBorder(borderRadius: BorderRadius.all(Radius.circular(24))),
      contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 12),
    ),
    useMaterial3: true,
  );
}
