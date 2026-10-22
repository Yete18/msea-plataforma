import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:google_sign_in/google_sign_in.dart';

/// Inicio de sesion con Google.
///
/// El ID de cliente OAuth (tipo "Aplicacion web") se pasa al compilar:
///   --dart-define=GOOGLE_CLIENT_ID=xxxx.apps.googleusercontent.com
/// En web se usa como clientId; en Android como serverClientId, para que el
/// id_token vaya dirigido al backend de MSEA, que lo verifica con Google.
/// Si no se define, el boton de Google no aparece.
class GoogleAuth {
  GoogleAuth._();

  static const String clientId = String.fromEnvironment('GOOGLE_CLIENT_ID');

  static bool get disponible => clientId.isNotEmpty;

  static Future<void>? _inicio;

  static Future<void> iniciar() {
    return _inicio ??= GoogleSignIn.instance.initialize(
      clientId: kIsWeb ? clientId : null,
      serverClientId: kIsWeb ? null : clientId,
    );
  }

  /// true en Android/iOS (boton propio que llama a authenticate()).
  /// En web Google exige su propio boton (ver google_boton.dart).
  static bool get usaBotonPropio => GoogleSignIn.instance.supportsAuthenticate();

  /// id_token de cada inicio de sesion correcto con Google.
  static Stream<String> get idTokens => GoogleSignIn.instance.authenticationEvents
      .map((evento) => switch (evento) {
            GoogleSignInAuthenticationEventSignIn() => evento.user.authentication.idToken,
            GoogleSignInAuthenticationEventSignOut() => null,
          })
      .where((token) => token != null && token.isNotEmpty)
      .cast<String>();

  /// Abre la seleccion de cuenta de Google (Android/iOS).
  static Future<void> autenticar() => GoogleSignIn.instance.authenticate();

  /// Al cerrar sesion en MSEA tambien se cierra en Google, para poder elegir otra cuenta.
  static Future<void> cerrar() async {
    if (!disponible) return;
    try {
      await iniciar();
      await GoogleSignIn.instance.signOut();
    } catch (_) {
      // Sin conexion con Google: la sesion de MSEA ya se cerro igual.
    }
  }
}
