import 'package:flutter/material.dart';

/// Paleta oficial de MSEA (misma que la web).
class MseaColors {
  static const green = Color(0xFF8CC63F);
  static const greenLight = Color(0xFFA8D858);
  static const greenDark = Color(0xFF315C3A);
  static const background = Color(0xFFF2F8E9);
  static const surface = Color(0xFFFFFFFF);
  static const text = Color(0xFF26332B);
  static const muted = Color(0xFF68736D);
  static const border = Color(0xFFE5E9E6);
  static const gold = Color(0xFFE6B84A);
  static const blue = Color(0xFF4D7EA8);
  static const red = Color(0xFFD95C5C);

  /// Texto sobre botones verdes (contraste AA).
  static const onGreen = Color(0xFF1F2A23);
}

class MseaTheme {
  static ThemeData light() {
    final scheme = ColorScheme.fromSeed(seedColor: MseaColors.green).copyWith(
      primary: MseaColors.green,
      onPrimary: MseaColors.onGreen,
      secondary: MseaColors.greenDark,
      onSecondary: Colors.white,
      tertiary: MseaColors.gold,
      error: MseaColors.red,
      surface: MseaColors.surface,
      onSurface: MseaColors.text,
      outline: MseaColors.border,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: MseaColors.background,
      // Requiere declarar las fuentes en pubspec si se empaquetan; si no, usa la del sistema.
      fontFamily: 'Nunito',
      appBarTheme: const AppBarTheme(
        backgroundColor: MseaColors.greenDark,
        foregroundColor: Colors.white,
        elevation: 0,
        centerTitle: false,
      ),
      cardTheme: CardThemeData(
        color: MseaColors.surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: MseaColors.border),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: MseaColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: MseaColors.greenDark, width: 2),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: MseaColors.green,
          foregroundColor: MseaColors.onGreen,
          minimumSize: const Size(48, 48),
          textStyle: const TextStyle(fontWeight: FontWeight.w800),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      navigationBarTheme: const NavigationBarThemeData(
        backgroundColor: Colors.white,
        indicatorColor: MseaColors.greenLight,
      ),
      snackBarTheme: const SnackBarThemeData(behavior: SnackBarBehavior.floating),
    );
  }
}
