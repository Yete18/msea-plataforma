<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Panel del padre o tutor. Todas las consultas parten de estudiante_padre:
 * un padre solo ve a los estudiantes vinculados a su cuenta.
 */
class PadreDashboardService
{
    public function __construct(
        private readonly ProfilePhotoService $fotos,
        private readonly GamificationService $gamificacion,
    ) {}

    public function idsHijos(int $idUsuarioPadre): array
    {
        return DB::table('estudiante_padre as ep')
            ->join('padres as p', 'p.id_padre', '=', 'ep.id_padre')
            ->join('estudiantes as e', 'e.id_estudiante', '=', 'ep.id_estudiante')
            ->join('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->where('p.id_usuario', $idUsuarioPadre)
            ->where('u.activo', true)
            ->pluck('ep.id_estudiante')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function hijos(int $idUsuarioPadre): Collection
    {
        return collect($this->idsHijos($idUsuarioPadre))->map(fn (int $id) => $this->resumenHijo($id));
    }

    public function resumenHijo(int $idEstudiante): array
    {
        $alumno = DB::table('estudiantes as e')
            ->join('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->leftJoin('profesores as p', 'p.id_profesor', '=', 'e.id_profesor')
            ->leftJoin('usuarios as up', 'up.id_usuario', '=', 'p.id_usuario')
            ->leftJoin('secciones as s', 's.id_seccion', '=', 'e.id_seccion')
            ->leftJoin('elencos as el', 'el.id_elenco', '=', 'e.id_elenco')
            ->where('e.id_estudiante', $idEstudiante)
            ->first([
                'e.*', 'u.nombres', 'u.apellido_paterno', 'u.apellido_materno', 'u.foto',
                'up.id_usuario as profesor_usuario', 'up.nombres as profesor_nombres', 'up.apellido_paterno as profesor_apellido',
                's.nombre as nivel_formativo', 'el.nombre as elenco',
            ]);

        $resumen = $this->gamificacion->resumen($idEstudiante);

        $porDia = DB::table('practicas_autonomas')
            ->where('id_estudiante', $idEstudiante)
            ->where('estado', 'finalizada')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->get(['created_at', 'duracion_segundos'])
            ->groupBy(fn ($p) => substr((string) $p->created_at, 0, 10))
            ->map(fn ($dia) => $dia->sum('duracion_segundos'));

        $dias = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
        $semana = [];
        for ($i = 6; $i >= 0; $i--) {
            $fecha = now()->subDays($i);
            $semana[] = ['dia' => $dias[$fecha->dayOfWeek], 'minutos' => round(($porDia[$fecha->toDateString()] ?? 0) / 60, 1)];
        }

        $estudiante = (object) ['id_estudiante' => $idEstudiante, 'id_elenco' => $alumno->id_elenco, 'id_profesor' => $alumno->id_profesor];
        $tareas = app(TareaService::class)->tareasVisiblesPara($estudiante)
            ->leftJoin('entregas_tareas as et', function ($join) use ($idEstudiante) {
                $join->on('et.id_tarea', '=', 't.id_tarea')->where('et.id_estudiante', '=', $idEstudiante);
            })
            ->orderByDesc('t.fecha_creacion')
            ->limit(30)
            ->get(['t.titulo', 't.fecha_limite', 't.xp_recompensa', 'et.estado', 'et.calificacion', 'et.comentario_profesor'])
            ->map(fn ($t) => [
                'titulo' => $t->titulo,
                'limite' => $t->fecha_limite ? date('d/m/Y', strtotime($t->fecha_limite)) : 'Sin fecha',
                'xp' => (int) $t->xp_recompensa,
                'estado' => $t->estado ?: 'pendiente',
                'calificacion' => $t->calificacion !== null ? (int) round((float) $t->calificacion) : null,
                'comentario_profesor' => $t->comentario_profesor,
            ])
            ->all();

        $logros = DB::table('estudiante_logro as el')
            ->join('logros as l', 'l.id_logro', '=', 'el.id_logro')
            ->where('el.id_estudiante', $idEstudiante)
            ->orderByDesc('el.desbloqueado_at')
            ->get(['l.nombre', 'l.descripcion', 'el.desbloqueado_at'])
            ->map(fn ($l) => ['nombre' => $l->nombre, 'desc' => $l->descripcion, 'desbloqueado_at' => date('d/m/Y', strtotime($l->desbloqueado_at))])
            ->all();

        $lecciones = DB::table('leccion_progreso')->where('id_estudiante', $idEstudiante)->where('estado', 'completada')->count();

        return [
            'id' => $idEstudiante,
            'nombre' => trim($alumno->nombres.' '.$alumno->apellido_paterno.' '.$alumno->apellido_materno),
            'foto' => $this->fotos->publicValue($alumno->foto, (int) $alumno->id_usuario),
            'nivel' => $resumen['nivel'],
            'xp_total' => $resumen['xp_total'],
            'racha' => $resumen['racha_actual'],
            'posicion' => $resumen['posicion'],
            'logros_total' => $resumen['logros'],
            'nivel_formativo' => $alumno->nivel_formativo,
            'elenco' => $alumno->elenco,
            'profesor' => $alumno->profesor_nombres ? trim($alumno->profesor_nombres.' '.$alumno->profesor_apellido) : null,
            'profesor_usuario' => $alumno->profesor_usuario ? (int) $alumno->profesor_usuario : null,
            'instrumentos' => DB::table('usuario_instrumento as ui')
                ->join('instrumentos as i', 'i.id_instrumento', '=', 'ui.id_instrumento')
                ->where('ui.id_usuario', $alumno->id_usuario)
                ->pluck('i.nombre')->implode(', ') ?: 'Sin instrumento',
            'semana_practica' => $semana,
            'tareas' => $tareas,
            'logros' => $logros,
            'lecciones_completadas' => $lecciones,
            'total_minutos' => round((int) DB::table('practicas_autonomas')->where('id_estudiante', $idEstudiante)->where('estado', 'finalizada')->sum('duracion_segundos') / 60, 1),
        ];
    }
}
