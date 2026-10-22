import 'dart:async';

import 'package:flutter/material.dart';

import '../api/msea_api.dart';
import '../theme/msea_theme.dart';
import 'estudiante_screens.dart';
import 'widgets.dart';

typedef LogoutCallback = Future<void> Function({bool todas});

/// Contenedor principal: muestra pestañas segun el rol del usuario.
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({
    super.key,
    required this.api,
    required this.session,
    required this.onLogout,
    required this.onSessionExpired,
  });

  final MseaApi api;
  final SessionData session;
  final LogoutCallback onLogout;
  final VoidCallback onSessionExpired;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  int _pestana = 0;

  bool get _esEstudiante => widget.session.user.role == 'estudiante';

  @override
  Widget build(BuildContext context) {
    final ctx = PantallaCtx(api: widget.api, onSessionExpired: widget.onSessionExpired);

    final pestanas = <(String, IconData, Widget)>[
      ('Inicio', Icons.home_outlined, InicioScreen(ctx: ctx)),
      if (_esEstudiante) ('Tareas', Icons.assignment_outlined, TareasScreen(ctx: ctx)),
      if (_esEstudiante) ('Práctica', Icons.music_note_outlined, PracticaScreen(ctx: ctx)),
      ('Avisos', Icons.notifications_outlined, NotificacionesScreen(ctx: ctx)),
      ('Cuenta', Icons.person_outline, CuentaScreen(ctx: ctx, usuario: widget.session.user, onLogout: widget.onLogout)),
    ];
    final actual = _pestana.clamp(0, pestanas.length - 1);

    return Scaffold(
      appBar: AppBar(title: Text(pestanas[actual].$1)),
      body: SafeArea(child: IndexedStack(index: actual, children: [for (final p in pestanas) p.$3])),
      bottomNavigationBar: NavigationBar(
        selectedIndex: actual,
        onDestinationSelected: (i) => setState(() => _pestana = i),
        destinations: [for (final p in pestanas) NavigationDestination(icon: Icon(p.$2), label: p.$1)],
      ),
    );
  }
}

/// Resumen segun rol, con datos reales de `/api/mobile/me`.
class InicioScreen extends StatefulWidget {
  const InicioScreen({super.key, required this.ctx});

  final PantallaCtx ctx;

  @override
  State<InicioScreen> createState() => _InicioScreenState();
}

class _InicioScreenState extends State<InicioScreen> {
  late Future<DashboardData> _datos;

  @override
  void initState() {
    super.initState();
    _datos = widget.ctx.llamar(widget.ctx.api.me);
  }

  Future<void> _recargar() async {
    final f = widget.ctx.llamar(widget.ctx.api.me);
    setState(() => _datos = f);
    await f.catchError((_) => DashboardData(usuario: MseaUser.fromJson(const {}), dashboard: const {}));
  }

  @override
  Widget build(BuildContext context) {
    return Cargador<DashboardData>(
      future: _datos,
      onRetry: _recargar,
      builder: (d) => RefreshIndicator(
        onRefresh: _recargar,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text('Hola, ${d.usuario.name}', style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900)),
            const SizedBox(height: 4),
            Text(_rolTexto(d.usuario.role), style: const TextStyle(color: MseaColors.muted, fontWeight: FontWeight.w700)),
            const SizedBox(height: 16),
            ..._contenido(d),
          ],
        ),
      ),
    );
  }

  String _rolTexto(String rol) => switch (rol) {
        'profesor' => 'Panel del profesor',
        'padre' => 'Seguimiento familiar',
        'director' => 'Dirección MSEA',
        _ => 'Panel del estudiante',
      };

  List<Widget> _contenido(DashboardData d) {
    if (d.dashboard.isEmpty) {
      return const [Vacio(icono: Icons.info_outline, texto: 'Tu perfil aún no tiene datos. Consulta con la dirección de MSEA.')];
    }

    switch (d.usuario.role) {
      case 'estudiante':
        final xpNivel = d.entero('xp_en_nivel');
        final porNivel = d.entero('xp_por_nivel').clamp(1, 1 << 30);
        return [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Nivel ${d.entero('nivel')}', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900)),
                const SizedBox(height: 8),
                LinearProgressIndicator(value: xpNivel / porNivel, minHeight: 10, borderRadius: BorderRadius.circular(8)),
                const SizedBox(height: 6),
                Text('$xpNivel / $porNivel XP para el siguiente nivel · ${d.entero('xp_total')} XP en total',
                    style: const TextStyle(color: MseaColors.muted)),
              ]),
            ),
          ),
          const SizedBox(height: 12),
          GridDatos(datos: [
            Dato(Icons.local_fire_department_outlined, '${d.entero('racha_actual')}', 'Días de racha', MseaColors.gold),
            Dato(Icons.leaderboard_outlined, d.enteroN('posicion') != null ? '#${d.entero('posicion')}' : '-', 'Ranking', MseaColors.blue),
            Dato(Icons.assignment_late_outlined, '${d.entero('tareas_pendientes')}', 'Tareas pendientes', MseaColors.red),
            Dato(Icons.emoji_events_outlined, '${d.entero('logros')}', 'Logros', MseaColors.greenDark),
          ]),
          const SizedBox(height: 12),
          Card(
            child: ListTile(
              leading: const Icon(Icons.timer_outlined),
              title: const Text('XP de práctica hoy'),
              subtitle: Text('${d.entero('xp_practica_hoy')} de ${d.entero('tope_diario_practica')} XP diarios'),
            ),
          ),
        ];
      case 'profesor':
        return [
          GridDatos(datos: [
            Dato(Icons.groups_outlined, '${d.entero('totalAlumnos')}', 'Alumnos', MseaColors.greenDark),
            Dato(Icons.assignment_outlined, '${d.entero('tareas')}', 'Tareas creadas', MseaColors.blue),
            Dato(Icons.rate_review_outlined, '${d.entero('entregasPendientes')}', 'Entregas por revisar', MseaColors.gold),
          ]),
          const SizedBox(height: 12),
          const Text('Para calificar entregas y escuchar grabaciones usa el panel web.', style: TextStyle(color: MseaColors.muted)),
        ];
      case 'director':
        return [
          GridDatos(datos: [
            Dato(Icons.school_outlined, '${d.entero('totalEstudiantes')}', 'Estudiantes', MseaColors.greenDark),
            Dato(Icons.person_outline, '${d.entero('totalProfesores')}', 'Profesores', MseaColors.blue),
            Dato(Icons.assignment_outlined, '${d.entero('totalTareas')}', 'Tareas', MseaColors.gold),
            Dato(Icons.music_note_outlined, '${d.entero('totalPracticas')}', 'Prácticas', MseaColors.green),
          ]),
          const SizedBox(height: 12),
          const Text('La gestión de usuarios, cursos y reportes está en el panel web.', style: TextStyle(color: MseaColors.muted)),
        ];
      case 'padre':
        final hijos = (d.dashboard['estudiantes'] as List? ?? const []).whereType<Map>().toList();
        if (hijos.isEmpty) {
          return const [Vacio(icono: Icons.family_restroom, texto: 'Sin estudiantes vinculados. Solicite a la dirección que vincule a su hijo o hija.')];
        }
        return [for (final h in hijos) _TarjetaHijo(hijo: Map<String, dynamic>.from(h))];
      default:
        return const [];
    }
  }
}

class _TarjetaHijo extends StatelessWidget {
  const _TarjetaHijo({required this.hijo});

  final Map<String, dynamic> hijo;

  @override
  Widget build(BuildContext context) {
    final tareas = (hijo['tareas'] as List? ?? const []).whereType<Map>().map((t) => Tarea.fromJson(Map<String, dynamic>.from(t))).toList();
    final posicion = hijo['posicion'];
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${hijo['nombre'] ?? ''}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
            const SizedBox(height: 10),
            GridDatos(datos: [
              Dato(Icons.star_outline, '${hijo['nivel'] ?? 1}', 'Nivel (${hijo['xp_total'] ?? 0} XP)', MseaColors.greenDark),
              Dato(Icons.local_fire_department_outlined, '${hijo['racha'] ?? 0}', 'Días de racha', MseaColors.gold),
              Dato(Icons.leaderboard_outlined, posicion != null ? '#$posicion' : '-', 'Ranking', MseaColors.blue),
              Dato(Icons.timer_outlined, '${hijo['total_minutos'] ?? 0}', 'Minutos de práctica', MseaColors.green),
            ]),
            const SizedBox(height: 10),
            const Text('Tareas', style: TextStyle(fontWeight: FontWeight.w800)),
            if (tareas.isEmpty) const Text('Aún no tiene tareas asignadas.', style: TextStyle(color: MseaColors.muted)),
            for (final t in tareas.take(5))
              ListTile(
                contentPadding: EdgeInsets.zero,
                dense: true,
                title: Text(t.titulo),
                subtitle: t.comentarioProfesor != null ? Text('Observación: ${t.comentarioProfesor}') : null,
                trailing: EstadoChip(tarea: t),
              ),
          ]),
        ),
      ),
    );
  }
}

/// Notificaciones del usuario.
class NotificacionesScreen extends StatefulWidget {
  const NotificacionesScreen({super.key, required this.ctx});

  final PantallaCtx ctx;

  @override
  State<NotificacionesScreen> createState() => _NotificacionesScreenState();
}

class _NotificacionesScreenState extends State<NotificacionesScreen> {
  late Future<List<Notificacion>> _datos;

  @override
  void initState() {
    super.initState();
    _datos = widget.ctx.llamar(widget.ctx.api.notificaciones);
  }

  Future<void> _recargar() async {
    final f = widget.ctx.llamar(widget.ctx.api.notificaciones);
    setState(() => _datos = f);
    await f.catchError((_) => <Notificacion>[]);
  }

  Future<void> _marcarLeidas() async {
    try {
      await widget.ctx.llamar(widget.ctx.api.marcarNotificacionesLeidas);
      await _recargar();
    } on ApiException catch (e) {
      if (mounted) avisar(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Cargador<List<Notificacion>>(
      future: _datos,
      onRetry: _recargar,
      builder: (lista) => RefreshIndicator(
        onRefresh: _recargar,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (lista.any((n) => !n.leida))
              Align(
                alignment: Alignment.centerRight,
                child: TextButton.icon(onPressed: _marcarLeidas, icon: const Icon(Icons.done_all), label: const Text('Marcar todo como leído')),
              ),
            if (lista.isEmpty) const Vacio(icono: Icons.notifications_none, texto: 'No hay notificaciones nuevas.'),
            for (final n in lista)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Card(
                  child: ListTile(
                    leading: Icon(n.leida ? Icons.notifications_none : Icons.notifications_active_outlined,
                        color: n.leida ? MseaColors.muted : MseaColors.greenDark),
                    title: Text(n.titulo, style: TextStyle(fontWeight: n.leida ? FontWeight.w600 : FontWeight.w900)),
                    subtitle: Text('${n.mensaje}\n${formatearFecha(n.fecha)}'),
                    isThreeLine: true,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// Datos de la cuenta y sesiones abiertas en otros dispositivos.
class CuentaScreen extends StatefulWidget {
  const CuentaScreen({super.key, required this.ctx, required this.usuario, required this.onLogout});

  final PantallaCtx ctx;
  final MseaUser usuario;
  final LogoutCallback onLogout;

  @override
  State<CuentaScreen> createState() => _CuentaScreenState();
}

class _CuentaScreenState extends State<CuentaScreen> {
  late Future<List<SesionMovil>> _sesiones;

  @override
  void initState() {
    super.initState();
    _sesiones = widget.ctx.llamar(widget.ctx.api.sesiones);
  }

  Future<void> _recargar() async {
    final f = widget.ctx.llamar(widget.ctx.api.sesiones);
    setState(() => _sesiones = f);
    await f.catchError((_) => <SesionMovil>[]);
  }

  Future<void> _cerrar(SesionMovil s) async {
    try {
      await widget.ctx.llamar(() => widget.ctx.api.cerrarSesionRemota(s.id));
      if (mounted) avisar(context, 'Sesión cerrada en ${s.dispositivo}.');
      await _recargar();
    } on ApiException catch (e) {
      if (mounted) avisar(context, e.message, error: true);
    }
  }

  Future<void> _confirmarSalirTodo() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Cerrar todas las sesiones'),
        content: const Text('Se cerrará la sesión en todos los teléfonos donde hayas ingresado, incluido este.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Cerrar todas')),
        ],
      ),
    );
    if (ok == true) await widget.onLogout(todas: true);
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: ListTile(
            leading: const CircleAvatar(backgroundColor: MseaColors.greenLight, foregroundColor: MseaColors.text, child: Icon(Icons.person)),
            title: Text(widget.usuario.name, style: const TextStyle(fontWeight: FontWeight.w900)),
            subtitle: Text(widget.usuario.email),
          ),
        ),
        const SizedBox(height: 16),
        const Text('Sesiones abiertas en la app', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
        const SizedBox(height: 8),
        Cargador<List<SesionMovil>>(
          future: _sesiones,
          onRetry: _recargar,
          shrink: true,
          builder: (lista) => Column(children: [
            for (final s in lista)
              Card(
                child: ListTile(
                  leading: const Icon(Icons.smartphone),
                  title: Text(s.actual ? '${s.dispositivo} (este dispositivo)' : s.dispositivo),
                  subtitle: Text('Último uso: ${formatearFecha(s.ultimoUso)}'),
                  trailing: s.actual
                      ? null
                      : IconButton(tooltip: 'Cerrar esta sesión', icon: const Icon(Icons.logout), onPressed: () => _cerrar(s)),
                ),
              ),
          ]),
        ),
        const SizedBox(height: 16),
        FilledButton.icon(onPressed: () => widget.onLogout(), icon: const Icon(Icons.logout), label: const Text('Cerrar sesión')),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: _confirmarSalirTodo,
          icon: const Icon(Icons.phonelink_erase_outlined),
          label: const Text('Cerrar sesión en todos los dispositivos'),
        ),
        const SizedBox(height: 12),
        const Text('Para cambiar tu contraseña o correo usa el panel web.', style: TextStyle(color: MseaColors.muted)),
      ],
    );
  }
}

/// Helper compartido: ejecuta llamadas y avisa si la sesion expiro.
class PantallaCtx {
  PantallaCtx({required this.api, required this.onSessionExpired});

  final MseaApi api;
  final VoidCallback onSessionExpired;

  Future<T> llamar<T>(Future<T> Function() fn) async {
    try {
      return await fn();
    } on ApiException catch (e) {
      if (e.sesionExpirada) scheduleMicrotask(onSessionExpired);
      rethrow;
    }
  }
}
