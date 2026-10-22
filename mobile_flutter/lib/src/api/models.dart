int _int(dynamic v) => v is num ? v.toInt() : int.tryParse('$v') ?? 0;
int? _intN(dynamic v) => v == null ? null : _int(v);
String _str(dynamic v, [String d = '']) => v == null ? d : v.toString();

class MseaUser {
  MseaUser({required this.id, required this.name, required this.email, required this.role});

  final int id;
  final String name;
  final String email;
  final String role;

  factory MseaUser.fromJson(Map<String, dynamic> json) => MseaUser(
        id: _int(json['id']),
        name: _str(json['nombre'], 'Usuario MSEA'),
        email: _str(json['correo']),
        role: _str(json['rol'], 'estudiante'),
      );
}

class SessionData {
  SessionData({required this.token, required this.user});

  final String token;
  final MseaUser user;
}

class DashboardData {
  DashboardData({required this.usuario, required this.dashboard});

  final MseaUser usuario;
  final Map<String, dynamic> dashboard;

  int entero(String clave) => _int(dashboard[clave]);
  int? enteroN(String clave) => _intN(dashboard[clave]);
}

class Tarea {
  Tarea({
    required this.id,
    required this.titulo,
    required this.descripcion,
    required this.fechaLimite,
    required this.xp,
    required this.grupal,
    required this.estado,
    this.calificacion,
    this.comentarioProfesor,
  });

  final int id;
  final String titulo;
  final String descripcion;
  final String fechaLimite;
  final int xp;
  final bool grupal;
  final String estado;
  final double? calificacion;
  final String? comentarioProfesor;

  bool get puedeEntregar => estado == 'pendiente' || estado == 'devuelta';

  String get estadoTexto => switch (estado) {
        'entregada' => 'Entregada',
        'devuelta' => 'Para corregir',
        'calificada' => 'Calificada',
        _ => 'Pendiente',
      };

  factory Tarea.fromJson(Map<String, dynamic> j) => Tarea(
        id: _int(j['id']),
        titulo: _str(j['titulo']),
        descripcion: _str(j['descripcion']),
        fechaLimite: _str(j['fecha_limite'] ?? j['limite']),
        xp: _int(j['xp']),
        grupal: j['grupal'] == true,
        estado: _str(j['estado'], 'pendiente'),
        calificacion: j['calificacion'] is num ? (j['calificacion'] as num).toDouble() : null,
        comentarioProfesor: j['comentario_profesor']?.toString(),
      );
}

class ResultadoPractica {
  ResultadoPractica({required this.mensaje, required this.xp, required this.duracionSegundos, required this.topeAlcanzado});

  final String mensaje;
  final int xp;
  final int duracionSegundos;
  final bool topeAlcanzado;

  factory ResultadoPractica.fromJson(Map<String, dynamic> j) => ResultadoPractica(
        mensaje: _str(j['message']),
        xp: _int(j['xp']),
        duracionSegundos: _int(j['duracion_segundos']),
        topeAlcanzado: j['tope_alcanzado'] == true,
      );
}

class Notificacion {
  Notificacion({required this.id, required this.titulo, required this.mensaje, required this.leida, required this.fecha});

  final int id;
  final String titulo;
  final String mensaje;
  final bool leida;
  final DateTime? fecha;

  factory Notificacion.fromJson(Map<String, dynamic> j) => Notificacion(
        id: _int(j['id']),
        titulo: _str(j['titulo']),
        mensaje: _str(j['mensaje']),
        leida: j['leida_at'] != null,
        fecha: DateTime.tryParse(_str(j['created_at'])),
      );
}

class SesionMovil {
  SesionMovil({required this.id, required this.dispositivo, required this.ultimoUso, required this.actual});

  final int id;
  final String dispositivo;
  final DateTime? ultimoUso;
  final bool actual;

  factory SesionMovil.fromJson(Map<String, dynamic> j) => SesionMovil(
        id: _int(j['id']),
        dispositivo: _str(j['dispositivo'], 'Dispositivo'),
        ultimoUso: DateTime.tryParse(_str(j['ultimo_uso'])),
        actual: j['actual'] == true,
      );
}

String formatearFecha(DateTime? f) {
  if (f == null) return '';
  final l = f.toLocal();
  String d(int n) => n.toString().padLeft(2, '0');
  return '${d(l.day)}/${d(l.month)}/${l.year} ${d(l.hour)}:${d(l.minute)}';
}
