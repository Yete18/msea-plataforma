import 'package:flutter/foundation.dart';

/// Configuracion de la app.
///
/// La URL del backend se inyecta en tiempo de compilacion:
///   flutter run --dart-define=MSEA_API_URL=http://10.0.2.2:8000          (desarrollo)
///   flutter build apk --dart-define=MSEA_API_URL=https://msea.ejemplo.pe  (produccion)
///
/// No existe una URL fija de produccion en el codigo. En modo release solo se
/// acepta HTTPS; si falta o es HTTP la app muestra un error de configuracion en
/// lugar de enviar credenciales sin cifrar.
class MseaConfig {
  MseaConfig._();

  static const String _apiUrl = String.fromEnvironment('MSEA_API_URL');

  /// Valor por defecto solo para depuracion, segun donde corre la app:
  /// - Chrome (web) y escritorio: el backend en el mismo PC -> 127.0.0.1:8000
  /// - Emulador Android: 10.0.2.2 es el "localhost" del PC
  /// (Antes siempre era 10.0.2.2, que en Chrome no existe y el login se quedaba
  /// esperando hasta el tiempo limite.)
  static String get _debugFallback {
    if (kIsWeb) return 'http://127.0.0.1:8000';
    if (defaultTargetPlatform == TargetPlatform.android) return 'http://10.0.2.2:8000';
    return 'http://127.0.0.1:8000';
  }

  static const Duration timeout = Duration(seconds: 20);

  static String get apiUrl {
    final url = (_apiUrl.isNotEmpty ? _apiUrl : (kReleaseMode ? '' : _debugFallback)).trim();
    return url.replaceAll(RegExp(r'/+$'), '');
  }

  /// Devuelve un mensaje si la configuracion no es valida para este modo.
  static String? get errorConfiguracion {
    final url = apiUrl;
    if (url.isEmpty) {
      return 'La aplicacion no tiene configurada la direccion del servidor (MSEA_API_URL).';
    }
    final uri = Uri.tryParse(url);
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
      return 'La direccion del servidor no es valida.';
    }
    if (kReleaseMode && uri.scheme != 'https') {
      return 'En produccion la direccion del servidor debe usar HTTPS.';
    }
    return null;
  }
}
