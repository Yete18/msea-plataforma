import 'dart:async';
import 'dart:convert';
import 'dart:io' show HandshakeException, SocketException;

import 'package:flutter/foundation.dart' show defaultTargetPlatform;
import 'package:http/http.dart' as http;

import '../config.dart';
import 'models.dart';
import 'session_store.dart';

export 'models.dart';

/// Error mostrado al usuario. [sesionExpirada] indica que hay que volver al login.
class ApiException implements Exception {
  ApiException(this.message, {this.sesionExpirada = false, this.errores = const {}});

  final String message;
  final bool sesionExpirada;
  final Map<String, List<String>> errores;

  @override
  String toString() => message;
}

/// Cliente de la API movil de MSEA (`/api/mobile/*`).
class MseaApi {
  MseaApi({String? baseUrl, http.Client? client, SessionStore? store})
      : baseUrl = (baseUrl ?? MseaConfig.apiUrl).replaceAll(RegExp(r'/+$'), ''),
        _client = client ?? http.Client(),
        _store = store ?? SecureSessionStore();

  final String baseUrl;
  final http.Client _client;
  final SessionStore _store;
  String? _token;

  bool get autenticado => _token != null;

  /// Restaura la sesion guardada y comprueba que el token siga vigente.
  Future<SessionData?> restaurarSesion() async {
    final token = await _store.leerToken();
    if (token == null || token.isEmpty) return null;
    _token = token;
    try {
      final data = await me();
      return SessionData(token: token, user: data.usuario);
    } on ApiException catch (e) {
      if (e.sesionExpirada) {
        await _limpiar();
        return null;
      }
      rethrow;
    }
  }

  /// Inicia sesión con correo y contraseña. El servidor decide el rol de la cuenta.
  Future<SessionData> login({required String email, required String password}) async {
    final data = await _send('POST', '/api/mobile/login', body: {
      'correo': email.trim().toLowerCase(),
      'contrasena': password,
      'device_name': 'App MSEA (${defaultTargetPlatform.name})',
    }, autenticar: false);

    final token = data['token'] as String?;
    if (token == null) throw ApiException('Respuesta inesperada del servidor.');
    _token = token;
    await _store.guardarToken(token);
    return SessionData(token: token, user: MseaUser.fromJson(_mapa(data['usuario'])));
  }

  /// Inicia sesion con el id_token que entrega Google.
  Future<SessionData> loginGoogle(String idToken) async {
    final data = await _send('POST', '/api/mobile/login/google', body: {
      'id_token': idToken,
      'device_name': 'App MSEA (${defaultTargetPlatform.name})',
    }, autenticar: false);

    final token = data['token'] as String?;
    if (token == null) throw ApiException('Respuesta inesperada del servidor.');
    _token = token;
    await _store.guardarToken(token);
    return SessionData(token: token, user: MseaUser.fromJson(_mapa(data['usuario'])));
  }

  Future<DashboardData> me() async {
    final data = await _send('GET', '/api/mobile/me');
    return DashboardData(
      usuario: MseaUser.fromJson(_mapa(data['usuario'])),
      dashboard: data['dashboard'] is Map ? _mapa(data['dashboard']) : const {},
    );
  }

  Future<List<Tarea>> tareas() async {
    final data = await _send('GET', '/api/mobile/tareas');
    return _lista(data['tareas']).map(Tarea.fromJson).toList();
  }

  Future<String> entregarTarea(int idTarea, {String? comentario}) async {
    final data = await _send('POST', '/api/mobile/tareas/$idTarea/entregar',
        body: {if (comentario != null && comentario.trim().isNotEmpty) 'comentario_estudiante': comentario.trim()});
    return data['message']?.toString() ?? 'Tarea entregada';
  }

  /// Inicia una practica: el servidor guarda la hora de inicio.
  Future<int> iniciarPractica(String tipo) async {
    final data = await _send('POST', '/api/mobile/practicas', body: {'tipo': tipo});
    return (data['id_practica'] as num).toInt();
  }

  /// Finaliza la practica. El servidor calcula la duracion y la XP;
  /// las metricas enviadas se guardan como no verificadas.
  Future<ResultadoPractica> finalizarPractica(int idPractica, {int? bpm}) async {
    final data = await _send('POST', '/api/mobile/practicas/$idPractica/finalizar', body: {if (bpm != null) 'bpm': bpm});
    return ResultadoPractica.fromJson(data);
  }

  Future<List<Notificacion>> notificaciones() async {
    final data = await _send('GET', '/api/mobile/notificaciones');
    return _lista(data['notificaciones']).map(Notificacion.fromJson).toList();
  }

  Future<void> marcarNotificacionesLeidas() => _send('POST', '/api/mobile/notificaciones/leidas');

  Future<List<SesionMovil>> sesiones() async {
    final data = await _send('GET', '/api/mobile/sesiones');
    return _lista(data['sesiones']).map(SesionMovil.fromJson).toList();
  }

  Future<void> cerrarSesionRemota(int id) => _send('DELETE', '/api/mobile/sesiones/$id');

  Future<void> logout({bool todas = false}) async {
    try {
      if (_token != null) await _send('POST', todas ? '/api/mobile/logout-todo' : '/api/mobile/logout');
    } on ApiException {
      // Si el servidor no responde igualmente se borra el token local.
    } finally {
      await _limpiar();
    }
  }

  // ---------------------------------------------------------------------

  Future<void> _limpiar() async {
    _token = null;
    await _store.borrarToken();
  }

  Future<Map<String, dynamic>> _send(String method, String path,
      {Map<String, dynamic>? body, bool autenticar = true}) async {
    final error = MseaConfig.errorConfiguracion;
    if (error != null && baseUrl == MseaConfig.apiUrl) throw ApiException(error);

    final request = http.Request(method, Uri.parse('$baseUrl$path'))
      ..headers.addAll({
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (autenticar && _token != null) 'Authorization': 'Bearer $_token',
      });
    if (body != null) request.body = jsonEncode(body);

    http.Response response;
    try {
      response = await http.Response.fromStream(await _client.send(request).timeout(MseaConfig.timeout));
    } on TimeoutException {
      throw ApiException('El servidor no respondió ($baseUrl). Verifica que el backend esté encendido e inténtalo de nuevo.');
    } on SocketException {
      throw ApiException('Sin conexión con el servidor ($baseUrl). Verifica que el backend esté encendido.');
    } on HandshakeException {
      throw ApiException('No se pudo establecer una conexión segura con el servidor.');
    } on http.ClientException {
      throw ApiException('No se pudo conectar con el servidor ($baseUrl). Verifica que el backend esté encendido.');
    }

    Map<String, dynamic> data = const {};
    if (response.body.isNotEmpty) {
      try {
        final decoded = jsonDecode(response.body);
        if (decoded is Map<String, dynamic>) data = decoded;
      } on FormatException {
        data = const {};
      }
    }

    final status = response.statusCode;
    if (status < 400) return data;

    if (status == 401 && autenticar) {
      await _limpiar();
      throw ApiException('Tu sesión expiró. Inicia sesión nuevamente.', sesionExpirada: true);
    }
    if (status == 429) throw ApiException('Demasiados intentos. Espera un minuto e inténtalo de nuevo.');
    if (status == 403) throw ApiException('No tienes permiso para esta acción.');
    if (status == 404) throw ApiException(data['message']?.toString() ?? 'No se encontró el recurso.');

    final errores = <String, List<String>>{};
    if (data['errors'] is Map) {
      (data['errors'] as Map).forEach((k, v) {
        errores[k.toString()] = v is List ? v.map((e) => e.toString()).toList() : [v.toString()];
      });
    }
    final mensaje = data['message']?.toString();
    if (status >= 500 || mensaje == null) {
      throw ApiException('Ocurrió un problema en el servidor. Inténtalo más tarde.');
    }
    throw ApiException(errores.isNotEmpty ? errores.values.first.first : mensaje, errores: errores);
  }

  static Map<String, dynamic> _mapa(dynamic v) => v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};

  static List<Map<String, dynamic>> _lista(dynamic v) =>
      v is List ? v.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : const [];
}
