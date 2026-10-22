<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProfesorDashboardService
{
    public function __construct(
        private readonly ProfilePhotoService $fotos,
        private readonly GamificationService $gamificacion,
    ) {}

    public function viewData(object $profesor): array
    {
        $usuario = DB::table('usuarios')->where('id_usuario', $profesor->id_usuario)->first();
        $alumnos = $this->alumnos((int) $profesor->id_profesor);
        $tareas = $this->tareas((int) $profesor->id_profesor);

        $especialidad = DB::table('usuario_instrumento as ui')
            ->join('instrumentos as i', 'i.id_instrumento', '=', 'ui.id_instrumento')
            ->where('ui.id_usuario', $profesor->id_usuario)
            ->orderBy('i.nombre')
            ->pluck('i.nombre')
            ->implode(', ');

        $pendientes = collect($tareas)->sum('entregas_por_revisar');

        return [
            'profesorData' => [
                'idProfesor' => (int) $profesor->id_profesor,
                'nombre' => trim($usuario->nombres.' '.$usuario->apellido_paterno.' '.$usuario->apellido_materno),
                'primerNombre' => $usuario->nombres,
                'nombres' => $usuario->nombres,
                'apellidoPaterno' => $usuario->apellido_paterno,
                'apellidoMaterno' => $usuario->apellido_materno,
                'celular' => $usuario->celular,
                'email' => $usuario->correo,
                'especialidad' => $especialidad ?: 'Sin especialidad',
                'foto' => $this->fotos->publicValue($usuario->foto, (int) $usuario->id_usuario),
                'totalAlumnos' => count($alumnos),
                'tareasRevision' => (int) $pendientes,
                'tareasCompletadas' => collect($tareas)->sum('entregas_calificadas'),
            ],
            'alumnosData' => $alumnos,
            'tareasData' => $tareas,
            'elencosData' => $this->elencos((int) $profesor->id_profesor),
            'ejerciciosData' => $this->ejercicios((int) $profesor->id_profesor),
            'tiposEjercicio' => DB::table('tipos_ejercicio')->orderBy('nombre')->pluck('nombre')->all(),
            'bibliotecaEscalas' => collect(array_keys(EscalaService::INSTRUMENTOS))
                ->mapWithKeys(fn ($i) => [$i => app(EscalaService::class)->catalogo($i)])->all(),
            'bibliotecaTecnica' => collect(array_keys(EscalaService::INSTRUMENTOS))
                ->mapWithKeys(fn ($i) => [$i => app(TecnicaService::class)->catalogo($i)])->all(),
            'notificacionesData' => $this->notificaciones((int) $profesor->id_usuario),
            'mensajesData' => app(MensajeService::class)->bandeja((int) $profesor->id_usuario),
            'practicaGrupoData' => $this->practicaGrupo(array_column($alumnos, 'id')),
        ];
    }

    /** Minutos practicados por todo el grupo en cada uno de los ultimos 7 dias. */
    public function practicaGrupo(array $idsEstudiantes): array
    {
        $porDia = $idsEstudiantes ? DB::table('practicas_autonomas')
            ->whereIn('id_estudiante', $idsEstudiantes)
            ->where('estado', 'finalizada')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->get(['created_at', 'duracion_segundos', 'id_estudiante'])
            ->groupBy(fn ($p) => substr((string) $p->created_at, 0, 10)) : collect();

        $dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

        return collect(range(6, 0))->map(function ($i) use ($porDia, $dias) {
            $fecha = now()->subDays($i);
            $grupo = $porDia[$fecha->toDateString()] ?? collect();

            return [
                'dia' => $dias[$fecha->dayOfWeek],
                'minutos' => (int) round($grupo->sum('duracion_segundos') / 60),
                'alumnos' => $grupo->pluck('id_estudiante')->unique()->count(),
                'hoy' => $i === 0,
            ];
        })->all();
    }

    public function alumnos(int $idProfesor): array
    {
        $driver = DB::connection()->getDriverName();
        $instrumentosSql = $driver === 'pgsql'
            ? "coalesce(string_agg(distinct i.nombre, ', '), 'Sin instrumento') as instrumento"
            : "coalesce(group_concat(distinct i.nombre), 'Sin instrumento') as instrumento";

        $filas = DB::table('estudiantes as e')
            ->join('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->leftJoin('usuario_instrumento as ui', 'ui.id_usuario', '=', 'u.id_usuario')
            ->leftJoin('instrumentos as i', 'i.id_instrumento', '=', 'ui.id_instrumento')
            ->leftJoin('gamificacion_perfiles as g', 'g.id_estudiante', '=', 'e.id_estudiante')
            ->leftJoin('elencos as el', 'el.id_elenco', '=', 'e.id_elenco')
            ->where('e.id_profesor', $idProfesor)
            ->where('u.activo', true)
            ->select([
                'e.id_estudiante', 'u.nombres', 'u.apellido_paterno', 'u.apellido_materno', 'u.foto', 'el.nombre as elenco',
                DB::raw($instrumentosSql),
                DB::raw('coalesce(g.xp_total, 0) as xp_total'),
                DB::raw('coalesce(g.racha_actual, 0) as racha'),
                'g.ultima_practica', 'u.id_usuario',
            ])
            ->groupBy('e.id_estudiante', 'u.nombres', 'u.apellido_paterno', 'u.apellido_materno', 'u.foto', 'el.nombre', 'g.xp_total', 'g.racha_actual', 'g.ultima_practica', 'u.id_usuario')
            ->orderBy('u.apellido_paterno')
            ->get();

        // Minutos de los ultimos 7 dias por alumno (una sola consulta).
        $ids = $filas->pluck('id_estudiante')->all();
        $minutos = $ids ? DB::table('practicas_autonomas')
            ->whereIn('id_estudiante', $ids)
            ->where('estado', 'finalizada')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('id_estudiante')
            ->select('id_estudiante', DB::raw('sum(duracion_segundos) as segundos'))
            ->pluck('segundos', 'id_estudiante') : collect();

        // Tareas calificadas por alumno (una sola consulta, ARQ-01).
        $calificadas = $ids ? DB::table('entregas_tareas')
            ->whereIn('id_estudiante', $ids)->where('estado', 'calificada')
            ->groupBy('id_estudiante')
            ->select('id_estudiante', DB::raw('count(*) as total'))
            ->pluck('total', 'id_estudiante') : collect();

        return $filas
            ->map(function ($alumno) use ($minutos, $calificadas) {
                $xp = (int) $alumno->xp_total;
                $tramo = (int) config('msea.xp.nivel_cada', 500);
                $completadas = (int) ($calificadas[$alumno->id_estudiante] ?? 0);

                return [
                    'id' => (int) $alumno->id_estudiante,
                    'nombre' => trim($alumno->nombres.' '.$alumno->apellido_paterno.' '.$alumno->apellido_materno),
                    'foto' => $this->fotos->publicValue($alumno->foto, (int) $alumno->id_usuario),
                    'instrumento' => $alumno->instrumento,
                    'elenco' => $alumno->elenco,
                    'nivel' => 'Nivel '.$this->gamificacion->levelForXp($xp),
                    'xp' => $xp % $tramo,
                    'xpMax' => $tramo,
                    'puntos' => $xp,
                    'racha' => $this->gamificacion->rachaVigente((object) ['ultima_practica' => $alumno->ultima_practica, 'racha_actual' => $alumno->racha]),
                    'completadas' => $completadas,
                    'idUsuario' => (int) $alumno->id_usuario,
                    'ultimaPractica' => $alumno->ultima_practica ? substr((string) $alumno->ultima_practica, 0, 10) : null,
                    'diasSinPracticar' => $alumno->ultima_practica
                        ? (int) Carbon::parse(substr((string) $alumno->ultima_practica, 0, 10))->startOfDay()->diffInDays(now()->startOfDay())
                        : null,
                    'minutosSemana' => round(((int) ($minutos[$alumno->id_estudiante] ?? 0)) / 60),
                ];
            })
            ->values()
            ->all();
    }

    /** Tareas individuales y de elenco del profesor, con conteo de entregas. */
    public function tareas(int $idProfesor): array
    {
        $tareas = DB::table('tareas as t')
            ->leftJoin('estudiantes as e', 'e.id_estudiante', '=', 't.id_estudiante')
            ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->leftJoin('elencos as el', 'el.id_elenco', '=', 't.id_elenco')
            ->where('t.id_profesor', $idProfesor)
            ->orderByDesc('t.fecha_creacion')
            ->orderByDesc('t.id_tarea')
            ->get([
                't.id_tarea', 't.titulo', 't.descripcion', 't.fecha_limite', 't.xp_recompensa', 't.estado', 't.id_estudiante', 't.id_elenco',
                't.material_nombre', 't.material_path', 'u.nombres', 'u.apellido_paterno', 'el.nombre as elenco',
                't.id_biblioteca', 't.config_escala',
            ]);

        // Conteo de entregas de todas las tareas en una sola consulta (ARQ-01).
        $conteos = DB::table('entregas_tareas as et')->join('estudiantes as e', 'e.id_estudiante', '=', 'et.id_estudiante')
            ->whereIn('et.id_tarea', $tareas->pluck('id_tarea')->all() ?: [0])->where('e.id_profesor', $idProfesor)
            ->groupBy('et.id_tarea')
            ->select('et.id_tarea')
            ->selectRaw("sum(case when et.estado = 'entregada' then 1 else 0 end) as por_revisar")
            ->selectRaw("sum(case when et.estado = 'calificada' then 1 else 0 end) as calificadas")
            ->selectRaw('count(*) as total')
            ->get()->keyBy('id_tarea');

        return $tareas
            ->map(function ($t) use ($conteos) {
                $conteo = $conteos[$t->id_tarea] ?? null;

                return [
                    'id' => (int) $t->id_tarea,
                    'titulo' => $t->titulo,
                    'descripcion' => $t->descripcion,
                    'destino' => $t->id_elenco ? 'elenco' : 'estudiante',
                    'id_estudiante' => $t->id_estudiante ? (int) $t->id_estudiante : null,
                    'id_elenco' => $t->id_elenco ? (int) $t->id_elenco : null,
                    'alumno' => $t->id_elenco ? 'Elenco: '.$t->elenco : trim($t->nombres.' '.$t->apellido_paterno),
                    'limite' => $t->fecha_limite,
                    'xp' => (int) $t->xp_recompensa,
                    'estado' => $t->estado ?: 'pendiente',
                    'material_nombre' => $t->material_nombre,
                    'material_url' => $t->material_path ? url('/archivos/tareas/'.$t->id_tarea) : null,
                    'id_biblioteca' => $t->id_biblioteca ? (int) $t->id_biblioteca : null,
                    'config_escala' => $t->config_escala ? json_decode($t->config_escala, true) : null,
                    'entregas_total' => (int) ($conteo->total ?? 0),
                    'entregas_por_revisar' => (int) ($conteo->por_revisar ?? 0),
                    'entregas_calificadas' => (int) ($conteo->calificadas ?? 0),
                ];
            })
            ->all();
    }

    public function elencos(int $idProfesor): array
    {
        return DB::table('elencos as el')
            ->join('estudiantes as e', 'e.id_elenco', '=', 'el.id_elenco')
            ->where('e.id_profesor', $idProfesor)
            ->groupBy('el.id_elenco', 'el.nombre')
            ->orderBy('el.nombre')
            ->get(['el.id_elenco', 'el.nombre', DB::raw('count(e.id_estudiante) as alumnos')])
            ->map(fn ($e) => ['id' => (int) $e->id_elenco, 'nombre' => $e->nombre, 'alumnos' => (int) $e->alumnos])
            ->all();
    }

    /** Biblioteca de ejercicios real (base de datos). */
    public function ejercicios(int $idProfesor): array
    {
        return DB::table('ejercicios as ej')
            ->join('tipos_ejercicio as te', 'te.id_tipo', '=', 'ej.id_tipo')
            ->where('ej.creado_por', $idProfesor)
            ->orderByDesc('ej.id_ejercicio')
            ->limit(30)
            ->get(['ej.id_ejercicio', 'ej.titulo', 'ej.descripcion', 'ej.dificultad', 'ej.xp', 'te.nombre as tipo'])
            ->map(fn ($e) => [
                'id' => (int) $e->id_ejercicio,
                'nombre' => $e->titulo ?: $e->tipo,
                'desc' => $e->descripcion ?: '',
                'tipo' => $e->tipo,
                'dificultad' => $e->dificultad,
                'xp' => (int) $e->xp,
            ])
            ->all();
    }

    public function notificaciones(int $idUsuario): array
    {
        return DB::table('notificaciones')
            ->where('id_usuario', $idUsuario)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($n) => [
                'id' => (int) $n->id_notificacion,
                'titulo' => $n->titulo,
                'texto' => $n->mensaje,
                'tipo' => $n->tipo,
                'fecha' => $n->created_at,
                'leida' => (bool) $n->leida_at,
            ])
            ->all();
    }

    public function progresoEstudiante(object $profesor, int $idEstudiante): ?array
    {
        $estudiante = DB::table('estudiantes as e')
            ->join('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->where('e.id_estudiante', $idEstudiante)
            ->where('e.id_profesor', $profesor->id_profesor)
            ->select('e.id_estudiante', 'u.nombres', 'u.apellido_paterno')
            ->first();

        if (! $estudiante) {
            return null;
        }

        $historial = collect()
            ->merge(DB::table('practicas_autonomas')->where('id_estudiante', $idEstudiante)->where('estado', 'finalizada')
                ->orderByDesc('created_at')->limit(10)->get()
                ->map(fn ($p) => [
                    'tipo' => 'Practica: '.$p->tipo,
                    'fecha' => (string) ($p->finalizada_at ?? $p->created_at),
                    'detalle' => round($p->duracion_segundos / 60, 1).' min (medido) | +'.$p->xp_ganado.' XP',
                ]))
            ->merge(DB::table('sesiones_afinacion')->where('id_estudiante', $idEstudiante)
                ->orderByDesc('created_at')->limit(5)->get()
                ->map(fn ($a) => [
                    'tipo' => 'Afinador (no verificado)',
                    'fecha' => (string) $a->created_at,
                    'detalle' => 'Nota: '.($a->nota_detectada ?? '-').' ('.($a->frecuencia ?? '-').' Hz) | Desv: '.round((float) ($a->desviacion_cents ?? 0), 1).' cents',
                ]))
            ->merge(DB::table('sesiones_ritmo')->where('id_estudiante', $idEstudiante)
                ->orderByDesc('created_at')->limit(5)->get()
                ->map(fn ($r) => [
                    'tipo' => 'Ritmo (medido en el navegador)',
                    'fecha' => (string) $r->created_at,
                    'detalle' => 'BPM: '.$r->bpm.' | Precision: '.($r->precision !== null ? round((float) $r->precision, 1).'%' : 'no medida'),
                ]))
            // Orden por la fecha real (timestamp), no por texto dd/mm/aaaa.
            ->sortByDesc(fn ($item) => strtotime($item['fecha']) ?: 0)
            ->take(15)
            ->values()
            ->map(fn ($item) => $item + ['fecha_texto' => date('d/m/Y H:i', strtotime($item['fecha']) ?: time())])
            ->all();

        $resumen = $this->gamificacion->resumen($idEstudiante);

        return [
            'estudiante' => [
                'nombre' => trim($estudiante->nombres.' '.$estudiante->apellido_paterno),
                'totalMinutos' => round((int) DB::table('practicas_autonomas')->where('id_estudiante', $idEstudiante)->where('estado', 'finalizada')->sum('duracion_segundos') / 60, 1),
                'ejerciciosHechos' => DB::table('entregas_tareas')->where('id_estudiante', $idEstudiante)->where('estado', 'calificada')->count(),
                'leccionesCompletadas' => DB::table('leccion_progreso')->where('id_estudiante', $idEstudiante)->where('estado', 'completada')->count(),
                'afinacionesHechas' => DB::table('sesiones_afinacion')->where('id_estudiante', $idEstudiante)->count(),
                'precisionRitmo' => round((float) (DB::table('sesiones_ritmo')->where('id_estudiante', $idEstudiante)->whereNotNull('precision')->avg('precision') ?? 0), 1),
                'xp' => $resumen['xp_total'],
                'nivel' => $resumen['nivel'],
                'racha' => $resumen['racha_actual'],
                'teoriaFallos' => DB::table('teoria_intentos')->where('id_estudiante', $idEstudiante)->where('correcta', false)->count(),
            ],
            'historial' => $historial,
        ];
    }
}
