import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Persistencia del token de la API movil.
abstract class SessionStore {
  Future<String?> leerToken();
  Future<void> guardarToken(String token);
  Future<void> borrarToken();
}

/// Usa Keychain (iOS) / Keystore cifrado (Android). Nunca SharedPreferences.
class SecureSessionStore implements SessionStore {
  SecureSessionStore([FlutterSecureStorage? storage])
      : _storage = storage ??
            const FlutterSecureStorage(aOptions: AndroidOptions(encryptedSharedPreferences: true));

  static const _clave = 'msea_token';
  final FlutterSecureStorage _storage;

  @override
  Future<String?> leerToken() => _storage.read(key: _clave);

  @override
  Future<void> guardarToken(String token) => _storage.write(key: _clave, value: token);

  @override
  Future<void> borrarToken() => _storage.delete(key: _clave);
}

/// Almacen en memoria, usado en pruebas.
class MemorySessionStore implements SessionStore {
  String? token;

  @override
  Future<String?> leerToken() async => token;

  @override
  Future<void> guardarToken(String value) async => token = value;

  @override
  Future<void> borrarToken() async => token = null;
}
