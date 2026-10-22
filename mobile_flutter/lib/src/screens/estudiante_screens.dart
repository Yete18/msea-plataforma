import 'dart:async';

import 'package:flutter/material.dart';

import '../api/msea_api.dart';
import '../theme/msea_theme.dart';
import 'dashboard_screen.dart';
import 'widgets.dart';

/// Lista de tareas del estudiante con entrega (comentario).
/// La subida de grabaciones se hace desde la web; ver README.
class TareasScreen extends StatefulWidget {
  const TareasScreen({super.key, required this.ctx});

  final PantallaCtx ctx;

  @override
  State<TareasScreen> createState() => _TareasScreenState();
}

class _TareasScreenState extends State<TareasScreen> {
  late Future<List<Tarea>> _datos;

  @override
  void initState() {
    super.initState();
    _datos = widget.ctx.llamar(widget.ctx.api.tareas);
  }

  Future<void> _recargar() async {
    final f = widget.ctx.llamar(widget.ctx.api.tareas);
    setState(() => _datos = f);
    await f.catchError((_) => <Tarea>[]);
  }

  Future<void> _entregar(Tarea t) async {
    final comentario = TextEditingController();
    final enviar = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (c) => Padding(
        padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.of(c).viewInsets.bottom + 20),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text('Entregar: ${t.titulo}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
          const SizedBox(height: 12),
          TextField(
            controller: comentario,
            maxLength: 1000,
            maxLines: 4,
            decoration: const InputDecoration(labelText: 'Comentario para tu profesor (opcional)'),
          ),
          const Text('Si la tarea pide una grabación, súbela desde la página web de MSEA.', style: TextStyle(color: MseaColors.muted)),
          const SizedBox(height: 12),
          FilledButton.icon(onPressed: () => Navigator.pop(c, true), icon: const Icon(Icons.send), label: const Text('Entregar tarea')),
        ]),
      ),
    );
    if (enviar != true) {
      comentario.dispose();
      return;
    }
    try {
      final msg = await widget.ctx.llamar(() => widget.ctx.api.entregarTarea(t.id, comentario: comentario.text));
      if (mounted) avisar(context, msg);
      await _recargar();
    } on ApiException catch (e) {
      if (mounted) avisar(context, e.message, error: true);
    } finally {
      comentario.dispose();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Cargador<List<Tarea>>(
      future: _datos,
      onRetry: _recargar,
      builder: (lista) => RefreshIndicator(
        onRefresh: _recargar,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (lista.isEmpty) const Vacio(icono: Icons.assignment_turned_in_outlined, texto: 'No tienes tareas asignadas.'),
            for (final t in lista)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        Expanded(child: Text(t.titulo, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900))),
                        EstadoChip(tarea: t),
                      ]),
                      if (t.descripcion.isNotEmpty) ...[const SizedBox(height: 6), Text(t.descripcion)],
                      const SizedBox(height: 6),
                      Text('Vence: ${t.fechaLimite.isEmpty ? 'sin fecha' : t.fechaLimite} · +${t.xp} XP${t.grupal ? ' · Grupal' : ''}',
                          style: const TextStyle(color: MseaColors.muted, fontWeight: FontWeight.w700)),
                      if (t.comentarioProfesor != null && t.comentarioProfesor!.isNotEmpty) ...[
                        const SizedBox(height: 8),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(color: MseaColors.background, borderRadius: BorderRadius.circular(10)),
                          child: Text('Profesor: ${t.comentarioProfesor}'),
                        ),
                      ],
                      if (t.puedeEntregar) ...[
                        const SizedBox(height: 10),
                        Align(
                          alignment: Alignment.centerRight,
                          child: FilledButton.icon(
                            onPressed: () => _entregar(t),
                            icon: const Icon(Icons.upload_outlined),
                            label: Text(t.estado == 'devuelta' ? 'Volver a entregar' : 'Entregar'),
                          ),
                        ),
                      ],
                    ]),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// Practica autonoma medida por el servidor.
///
/// El cronometro en pantalla es solo informativo: la duracion valida es la que
/// calcula el servidor entre el inicio y el fin. La app no evalua la afinacion
/// ni el ritmo del instrumento; el pulso (BPM) que la persona escribe se guarda
/// como dato declarado y no verificado.
class PracticaScreen extends StatefulWidget {
  const PracticaScreen({super.key, required this.ctx});

  final PantallaCtx ctx;

  @override
  State<PracticaScreen> createState() => _PracticaScreenState();
}

class _PracticaScreenState extends State<PracticaScreen> {
  static const _tipos = {
    'tecnica': ('Técnica', Icons.fitness_center_outlined),
    'afinacion': ('Afinación', Icons.tune),
    'ritmo': ('Ritmo', Icons.av_timer),
    'teoria': ('Teoría', Icons.menu_book_outlined),
    'libre': ('Práctica libre', Icons.music_note_outlined),
  };

  String _tipo = 'tecnica';
  int? _idPractica;
  DateTime? _inicio;
  Timer? _timer;
  Duration _transcurrido = Duration.zero;
  bool _ocupado = false;
  final _bpm = TextEditingController();
  ResultadoPractica? _ultimo;

  @override
  void dispose() {
    _timer?.cancel();
    _bpm.dispose();
    super.dispose();
  }

  Future<void> _iniciar() async {
    setState(() => _ocupado = true);
    try {
      final id = await widget.ctx.llamar(() => widget.ctx.api.iniciarPractica(_tipo));
      _inicio = DateTime.now();
      _timer = Timer.periodic(const Duration(seconds: 1), (_) {
        if (mounted) setState(() => _transcurrido = DateTime.now().difference(_inicio!));
      });
      setState(() {
        _idPractica = id;
        _ultimo = null;
      });
    } on ApiException catch (e) {
      if (mounted) avisar(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _ocupado = false);
    }
  }

  Future<void> _finalizar() async {
    final id = _idPractica;
    if (id == null) return;
    setState(() => _ocupado = true);
    try {
      final bpm = int.tryParse(_bpm.text.trim());
      final r = await widget.ctx.llamar(() => widget.ctx.api.finalizarPractica(id, bpm: bpm));
      _timer?.cancel();
      setState(() {
        _idPractica = null;
        _transcurrido = Duration.zero;
        _ultimo = r;
        _bpm.clear();
      });
    } on ApiException catch (e) {
      if (mounted) avisar(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _ocupado = false);
    }
  }

  String _reloj(Duration d) =>
      '${d.inMinutes.toString().padLeft(2, '0')}:${(d.inSeconds % 60).toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final enCurso = _idPractica != null;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const Text('Elige qué vas a practicar', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
        const SizedBox(height: 8),
        Wrap(spacing: 8, runSpacing: 8, children: [
          for (final e in _tipos.entries)
            ChoiceChip(
              avatar: Icon(e.value.$2, size: 18),
              label: Text(e.value.$1),
              selected: _tipo == e.key,
              onSelected: enCurso ? null : (_) => setState(() => _tipo = e.key),
            ),
        ]),
        const SizedBox(height: 20),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(children: [
              Text(_reloj(_transcurrido), style: const TextStyle(fontSize: 48, fontWeight: FontWeight.w900, fontFeatures: [FontFeature.tabularFigures()])),
              Text(enCurso ? 'Práctica en curso' : 'Lista para empezar', style: const TextStyle(color: MseaColors.muted, fontWeight: FontWeight.w700)),
              if (enCurso) ...[
                const SizedBox(height: 14),
                TextField(
                  controller: _bpm,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Pulso trabajado en BPM (opcional)',
                    helperText: 'Dato declarado por ti; no se verifica.',
                  ),
                ),
              ],
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: _ocupado ? null : (enCurso ? _finalizar : _iniciar),
                  icon: Icon(enCurso ? Icons.stop_circle_outlined : Icons.play_arrow_rounded),
                  label: Text(enCurso ? 'Terminar práctica' : 'Empezar práctica'),
                ),
              ),
            ]),
          ),
        ),
        if (_ultimo != null) ...[
          const SizedBox(height: 12),
          Card(
            child: ListTile(
              leading: Icon(_ultimo!.xp > 0 ? Icons.emoji_events_outlined : Icons.info_outline,
                  color: _ultimo!.xp > 0 ? MseaColors.gold : MseaColors.muted),
              title: Text(_ultimo!.xp > 0 ? '+${_ultimo!.xp} XP' : 'Práctica registrada sin XP'),
              subtitle: Text('${_ultimo!.mensaje}\nDuración registrada: ${_reloj(Duration(seconds: _ultimo!.duracionSegundos))}'
                  '${_ultimo!.topeAlcanzado ? '\nAlcanzaste el máximo de XP de práctica de hoy.' : ''}'),
              isThreeLine: true,
            ),
          ),
        ],
        const SizedBox(height: 12),
        const Text(
          'El tiempo y la XP los calcula el servidor. Las prácticas muy cortas no suman XP y existe un máximo diario.',
          style: TextStyle(color: MseaColors.muted),
        ),
      ],
    );
  }
}
