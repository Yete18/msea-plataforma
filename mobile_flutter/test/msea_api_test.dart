import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:msea_mobile/src/api/msea_api.dart';
import 'package:msea_mobile/src/api/session_store.dart';

http.Response _json(Object body, [int status = 200]) =>
    http.Response(jsonEncode(body), status, headers: {'content-type': 'application/json'});

void main() {
  test('login guarda el token y lo envia como Bearer', () async {
    final store = MemorySessionStore();
    String? auth;
    final api = MseaApi(
      baseUrl: 'https://msea.test',
      store: store,
      client: MockClient((req) async {
        if (req.url.path == '/api/mobile/login') {
          final body = jsonDecode(req.body) as Map;
          expect(body['correo'], 'ana@msea.test');
          expect(body.containsKey('rol'), isFalse);
          return _json({'token': 'abc', 'usuario': {'id': 1, 'nombre': 'Ana', 'correo': 'ana@msea.test', 'rol': 'estudiante'}});
        }
        auth = req.headers['Authorization'];
        return _json({'tareas': []});
      }),
    );

    final s = await api.login(email: ' ANA@msea.test ', password: 'x');
    expect(s.user.name, 'Ana');
    expect(store.token, 'abc');
    await api.tareas();
    expect(auth, 'Bearer abc');
  });

  test('un 401 borra el token y marca la sesion como expirada', () async {
    final store = MemorySessionStore()..token = 'viejo';
    final api = MseaApi(
      baseUrl: 'https://msea.test',
      store: store,
      client: MockClient((_) async => _json({'message': 'Unauthenticated.'}, 401)),
    );

    expect(await api.restaurarSesion(), isNull);
    expect(store.token, isNull);
  });

  test('credenciales incorrectas muestran el mensaje del servidor', () async {
    final api = MseaApi(
      baseUrl: 'https://msea.test',
      store: MemorySessionStore(),
      client: MockClient((_) async => _json({'message': 'Correo electrónico o contraseña incorrectos.'}, 422)),
    );

    expect(
      () => api.login(email: 'a@b.pe', password: 'x'),
      throwsA(isA<ApiException>().having((e) => e.message, 'message', 'Correo electrónico o contraseña incorrectos.')),
    );
  });

  test('error 500 no expone detalles internos', () async {
    final api = MseaApi(
      baseUrl: 'https://msea.test',
      store: MemorySessionStore()..token = 't',
      client: MockClient((_) async => http.Response('<html>SQLSTATE...</html>', 500)),
    );
    await expectLater(api.tareas(), throwsA(isA<ApiException>().having((e) => e.message, 'm', contains('servidor'))));
  });

  test('practica: iniciar y finalizar usan los endpoints del servidor', () async {
    final rutas = <String>[];
    final api = MseaApi(
      baseUrl: 'https://msea.test',
      store: MemorySessionStore()..token = 't',
      client: MockClient((req) async {
        rutas.add('${req.method} ${req.url.path}');
        if (req.url.path.endsWith('/finalizar')) {
          return _json({'message': 'Practica registrada', 'xp': 16, 'duracion_segundos': 90, 'tope_alcanzado': false});
        }
        return _json({'id_practica': 7, 'iniciada_at': '2026-10-10T10:00:00Z'}, 201);
      }),
    );

    final id = await api.iniciarPractica('tecnica');
    final r = await api.finalizarPractica(id, bpm: 80);
    expect(rutas, ['POST /api/mobile/practicas', 'POST /api/mobile/practicas/7/finalizar']);
    expect(r.xp, 16);
  });
}
