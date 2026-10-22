import 'package:flutter/material.dart';

import '../api/msea_api.dart';
import '../theme/msea_theme.dart';

void avisar(BuildContext context, String texto, {bool error = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(texto), backgroundColor: error ? MseaColors.red : MseaColors.greenDark));
}

/// Muestra carga, error con reintento o el contenido.
class Cargador<T> extends StatelessWidget {
  const Cargador({super.key, required this.future, required this.builder, required this.onRetry, this.shrink = false});

  final Future<T> future;
  final Widget Function(T) builder;
  final Future<void> Function() onRetry;
  final bool shrink;

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<T>(
      future: future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Padding(padding: EdgeInsets.all(32), child: Center(child: CircularProgressIndicator()));
        }
        if (snap.hasError) {
          final msg = snap.error is ApiException ? (snap.error as ApiException).message : 'No se pudo cargar la información.';
          final contenido = Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off_outlined, size: 40, color: MseaColors.muted),
            const SizedBox(height: 8),
            Text(msg, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            OutlinedButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh), label: const Text('Reintentar')),
          ]);
          return shrink ? Padding(padding: const EdgeInsets.all(16), child: contenido) : Center(child: Padding(padding: const EdgeInsets.all(24), child: contenido));
        }
        return builder(snap.data as T);
      },
    );
  }
}

class Vacio extends StatelessWidget {
  const Vacio({super.key, required this.icono, required this.texto});

  final IconData icono;
  final String texto;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 32, horizontal: 16),
      child: Column(children: [
        Icon(icono, size: 40, color: MseaColors.muted),
        const SizedBox(height: 8),
        Text(texto, textAlign: TextAlign.center, style: const TextStyle(color: MseaColors.muted, fontWeight: FontWeight.w700)),
      ]),
    );
  }
}

class Dato {
  const Dato(this.icono, this.valor, this.etiqueta, this.color);

  final IconData icono;
  final String valor;
  final String etiqueta;
  final Color color;
}

class GridDatos extends StatelessWidget {
  const GridDatos({super.key, required this.datos});

  final List<Dato> datos;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: (context, c) {
      final columnas = c.maxWidth > 560 ? 4 : 2;
      final ancho = (c.maxWidth - (columnas - 1) * 10) / columnas;
      return Wrap(
        spacing: 10,
        runSpacing: 10,
        children: [
          for (final d in datos)
            SizedBox(
              width: ancho,
              child: Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: MseaColors.border),
                ),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Icon(d.icono, color: d.color),
                  const SizedBox(height: 6),
                  Text(d.valor, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900)),
                  Text(d.etiqueta, style: const TextStyle(color: MseaColors.muted, fontWeight: FontWeight.w700)),
                ]),
              ),
            ),
        ],
      );
    });
  }
}

class EstadoChip extends StatelessWidget {
  const EstadoChip({super.key, required this.tarea});

  final Tarea tarea;

  @override
  Widget build(BuildContext context) {
    final (Color color, String texto) = switch (tarea.estado) {
      'calificada' => (MseaColors.greenDark, tarea.calificacion != null ? '${tarea.calificacion!.toStringAsFixed(0)}/100' : 'Calificada'),
      'entregada' => (MseaColors.blue, 'Entregada'),
      'devuelta' => (MseaColors.red, 'Para corregir'),
      _ => (MseaColors.gold, 'Pendiente'),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(20)),
      child: Text(texto, style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 12)),
    );
  }
}
