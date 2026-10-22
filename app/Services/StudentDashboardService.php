<?php

namespace App\Services;

use App\Models\Estudiante;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class StudentDashboardService
{
    public function __construct(
        private readonly ProfilePhotoService $profilePhotoService,
        private readonly GamificationService $gamificacion,
        private readonly TareaService $tareas,
        private readonly TeoriaService $teoria,
        private readonly CursoService $cursos,
        private readonly HabitosService $habitos,
    ) {}

    public function dashboardPayload(int $idUsuario): ?array
    {
        $estudiante = $this->estudianteBase($idUsuario);

        if (! $estudiante) {
            return null;
        }

        $instrumentos = Usuario::query()
            ->with(['instrumentos' => fn ($q) => $q->select('instrumentos.id_instrumento', 'instrumentos.nombre')->orderBy('instrumentos.nombre')])
            ->whereKey($idUsuario)
            ->first()?->instrumentos->pluck('nombre')->all() ?? [];

        // Fuente unica: el mismo resumen que usan ranking, profesor, padre y director.
        $g = $this->gamificacion->resumen((int) $estudiante->id_estudiante);
        $tareas = $this->tareasData($estudiante);

        return [
            'dashboardData' => [
                'idEstudiante' => (int) $estudiante->id_estudiante,
                'nombre' => $estudiante->nombres,
                'apellidoPaterno' => $estudiante->apellido_paterno,
                'apellidoMaterno' => $estudiante->apellido_materno,
                'apellido' => trim(($estudiante->apellido_paterno ?? '').' '.($estudiante->apellido_materno ?? '')),
                'nombreCompleto' => trim($estudiante->nombres.' '.$estudiante->apellido_paterno.' '.$estudiante->apellido_materno),
                'email' => $estudiante->correo,
                'ci' => $estudiante->ci,
                'celular' => $estudiante->celular,
                'direccion' => $estudiante->direccion,
                'fechaNacimiento' => $estudiante->fecha_nacimiento,
                'foto' => $this->profilePhotoService->publicValue($estudiante->foto, (int) $estudiante->id_usuario),
                'instrumento' => $instrumentos ? implode(', ', $instrumentos) : 'Sin instrumento',
                'nivel' => $g['nivel'],
                'nivelTexto' => 'Nivel '.$g['nivel'],
                'xp' => $g['xp_en_nivel'],
                'xpMax' => $g['xp_por_nivel'],
                'puntos' => $g['xp_total'],
                'racha' => $g['racha_actual'],
                'mejorRacha' => $g['mejor_racha'],
                'ejerciciosHechos' => (int) DB::table('practicas_autonomas')->where('id_estudiante', $estudiante->id_estudiante)->where('estado', 'finalizada')->count(),
                'tareasCalificadas' => collect($tareas)->where('estado', 'calificada')->count(),
                'rankingPos' => $g['posicion'] ?? 0,
                'seccion' => $estudiante->seccion ?: 'Sin nivel',
                'sede' => $estudiante->elenco ?: 'Sin elenco',
                'elenco' => $estudiante->elenco,
                'profesor' => $estudiante->profesor_nombre ?: 'Sin profesor asignado',
                'miembroDesde' => $estudiante->fecha_ingreso ? date('d/m/Y', strtotime($estudiante->fecha_ingreso)) : 'Sin fecha',
                'logrosDesbloqueados' => $g['logros'],
                'practicaTopeDiario' => (int) config('msea.xp.tope_diario_practica'),
                'practicaXpHoy' => $this->gamificacion->xpDeFuenteHoy((int) $estudiante->id_estudiante, 'practica'),
                'tituloNivel' => $this->habitos->tituloNivel((int) $g['nivel']),
                'practicoHoy' => (bool) ($g['practico_hoy'] ?? false),
                'metaDiaria' => $estudiante->meta_diaria_minutos !== null ? (int) $estudiante->meta_diaria_minutos : null,
                'metasPosibles' => HabitosService::METAS,
                'minutosHoy' => $this->habitos->minutosHoy((int) $estudiante->id_estudiante),
                'semana' => $this->habitos->semanaActual((int) $estudiante->id_estudiante, $estudiante->meta_diaria_minutos ? (int) $estudiante->meta_diaria_minutos : null),
            ],
            'rankingData' => $this->rankingData((int) $estudiante->id_estudiante),
            'tareasData' => $tareas,
            'ejerciciosData' => $this->ejerciciosData(),
            'logrosData' => $this->logrosData((int) $estudiante->id_estudiante),
            'actividadData' => $this->actividadData((int) $estudiante->id_estudiante),
            'notificacionesData' => $this->notificacionesData($idUsuario),
            'cursosData' => $this->cursos->cursosDeEstudiante((int) $estudiante->id_estudiante),
            'teoriaData' => $this->teoria->preguntasPara($estudiante),
            'mensajesData' => app(MensajeService::class)->bandeja($idUsuario, 20),
            'contactosData' => app(MensajeService::class)->contactos($idUsuario, 'estudiante'),
        ];
    }

    public function estudianteExiste(int $idUsuario): bool
    {
        return Estudiante::query()->where('id_usuario', $idUsuario)->exists();
    }

    public function updateProfile(int $idUsuario, array $validated): array
    {
        $payload = [
            'nombres' => $validated['nombres'],
            'apellido_paterno' => $validated['apellido_paterno'] ?? null,
            'apellido_materno' => $validated['apellido_materno'] ?? null,
            'correo' => AuthService::normalizarCorreo($validated['correo']),
            'celular' => $validated['celular'] ?? null,
        ];

        if (array_key_exists('foto', $validated)) {
            $payload['foto'] = $this->profilePhotoService->storeIfBase64(
                $validated['foto'],
                (string) Usuario::query()->whereKey($idUsuario)->value('foto')
            );
        }

        Usuario::query()->whereKey($idUsuario)->update($payload);

        if (array_key_exists('foto', $payload)) {
            $payload['foto'] = $this->profilePhotoService->publicValue($payload['foto'], $idUsuario);
        }

        return $payload;
    }

    private function estudianteBase(int $idUsuario): ?object
    {
        $profesorNombreSql = DB::connection()->getDriverName() === 'pgsql'
            ? "concat_ws(' ', up.nombres, up.apellido_paterno) as profesor_nombre"
            : "trim(coalesce(up.nombres, '') || ' ' || coalesce(up.apellido_paterno, '')) as profesor_nombre";

        return DB::table('estudiantes as e')
            ->join('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->leftJoin('secciones as s', 's.id_seccion', '=', 'e.id_seccion')
            ->leftJoin('elencos as el', 'el.id_elenco', '=', 'e.id_elenco')
            ->leftJoin('profesores as p', 'p.id_profesor', '=', 'e.id_profesor')
            ->leftJoin('usuarios as up', 'up.id_usuario', '=', 'p.id_usuario')
            ->where('e.id_usuario', $idUsuario)
            ->select([
                'e.id_estudiante', 'e.id_usuario', 'e.id_elenco', 'e.id_profesor', 'e.fecha_ingreso', 'e.meta_diaria_minutos',
                'u.correo', 'u.nombres', 'u.apellido_paterno', 'u.apellido_materno', 'u.ci', 'u.celular',
                'u.direccion', 'u.fecha_nacimiento', 'u.foto',
                's.nombre as seccion', 'el.nombre as elenco',
                DB::raw($profesorNombreSql),
            ])
            ->first();
    }

    /** Top 10 de su grupo (elenco), solo con nombre de pila. Ver GamificationService::rankingDelGrupo. */
    private function rankingData(int $idEstudiante): array
    {
        return collect($this->gamificacion->rankingDelGrupo($idEstudiante))
            ->take(10)
            ->map(fn ($fila) => [
                'nombre' => $fila['nombre'],
                'puntos' => $fila['puntos'],
                'posicion' => $fila['posicion'],
                'esYo' => $fila['id_estudiante'] === $idEstudiante,
            ])->all();
    }

    /** Tareas propias y de su elenco, con estado de SU entrega, nota y observacion. */
    private function tareasData(object $estudiante): array
    {
        $idEstudiante = (int) $estudiante->id_estudiante;

        return $this->tareas->tareasVisiblesPara($estudiante)
            ->leftJoin('profesores as p', 'p.id_profesor', '=', 't.id_profesor')
            ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'p.id_usuario')
            ->leftJoin('entregas_tareas as et', function ($join) use ($idEstudiante) {
                $join->on('et.id_tarea', '=', 't.id_tarea')->where('et.id_estudiante', '=', $idEstudiante);
            })
            ->select([
                't.id_tarea', 't.titulo', 't.descripcion', 't.fecha_limite', 't.xp_recompensa', 't.id_elenco',
                't.material_nombre', 't.material_path', 't.id_biblioteca', 't.config_escala',
                'u.nombres as prof_nombres', 'u.apellido_paterno as prof_apellido',
                'et.id_entrega', 'et.estado as entrega_estado', 'et.calificacion', 'et.comentario_profesor',
                'et.comentario_estudiante', 'et.archivo_nombre', 'et.archivo_path', 'et.entregado_at',
            ])
            ->orderByRaw('case when t.fecha_limite is null then 1 else 0 end')
            ->orderBy('t.fecha_limite')
            ->get()
            ->map(fn ($t) => [
                'id' => (int) $t->id_tarea,
                'nombre' => $t->titulo,
                'profesor' => trim(($t->prof_nombres ?? 'Profesor').' '.($t->prof_apellido ?? '')),
                'vence' => $t->fecha_limite ? date('d/m/Y', strtotime($t->fecha_limite)) : 'Sin fecha',
                'venceIso' => $t->fecha_limite ? date('Y-m-d', strtotime($t->fecha_limite)) : null,
                'urgencia' => $this->urgenciaTarea($t->fecha_limite, $t->entrega_estado),
                'xp' => (int) $t->xp_recompensa,
                'desc' => $t->descripcion ?: '',
                'grupal' => (bool) $t->id_elenco,
                'estado' => $t->entrega_estado ?: 'pendiente',
                'calificacion' => $t->calificacion !== null ? (float) $t->calificacion : null,
                'comentarioProfesor' => $t->comentario_profesor,
                'comentarioEstudiante' => $t->comentario_estudiante,
                'archivoNombre' => $t->archivo_nombre,
                'archivoUrl' => $t->archivo_path ? url('/archivos/entregas/'.$t->id_entrega) : null,
                'materialNombre' => $t->material_nombre,
                'materialUrl' => $t->material_path ? url('/archivos/tareas/'.$t->id_tarea) : null,
                'puedeEntregar' => $t->entrega_estado !== 'calificada',
                'escalaUrl' => $t->id_biblioteca ? url('/dashboard-estudiante/tareas/'.$t->id_tarea.'/escala') : null,
                'escalaResumen' => $t->id_biblioteca ? $this->resumenEscala((int) $t->id_biblioteca, $t->config_escala) : null,
            ])
            ->all();
    }

    private function resumenEscala(int $idBiblioteca, ?string $config): ?string
    {
        $escalas = app(EscalaService::class);
        $escala = $escalas->escala($idBiblioteca, soloActivas: false);
        if (! $escala) {
            return null;
        }
        try {
            return $escalas->describir($escala, $escalas->normalizarConfig($config, $escala));
        } catch (\Throwable) {
            return null;
        }
    }

    private function ejerciciosData(): array
    {
        return DB::table('ejercicios as e')
            ->join('tipos_ejercicio as te', 'te.id_tipo', '=', 'e.id_tipo')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('tarea_ejercicio as tj')->whereColumn('tj.id_ejercicio', 'e.id_ejercicio'))
            ->select(['e.id_ejercicio', 'e.titulo', 'e.descripcion', 'e.dificultad', 'e.xp', 'te.nombre as tipo'])
            ->orderBy('e.id_ejercicio')
            ->limit(12)
            ->get()
            ->map(fn ($ejercicio) => [
                'id' => (int) $ejercicio->id_ejercicio,
                'nombre' => $ejercicio->titulo ?: ucfirst($ejercicio->tipo),
                'desc' => $ejercicio->descripcion ?: '',
                'tipo' => $this->tipoPractica($ejercicio->tipo),
                'categoria' => $ejercicio->tipo,
                'dificultad' => ucfirst($ejercicio->dificultad ?? 'basico'),
            ])
            ->all();
    }

    private function logrosData(int $idEstudiante): array
    {
        $desbloqueados = DB::table('estudiante_logro')->where('id_estudiante', $idEstudiante)->pluck('desbloqueado_at', 'id_logro');

        return DB::table('logros')->where('activo', true)->orderBy('id_logro')->get()
            ->map(fn ($logro) => [
                'nombre' => $logro->nombre,
                'desc' => $logro->descripcion,
                'xpBonus' => (int) $logro->xp_bonus,
                'bloqueado' => ! $desbloqueados->has($logro->id_logro),
                'fecha' => $desbloqueados->has($logro->id_logro) ? date('d/m/Y', strtotime($desbloqueados[$logro->id_logro])) : null,
            ])
            ->all();
    }

    /** Historial desde el libro de XP: misma fuente que el total. */
    private function actividadData(int $idEstudiante): array
    {
        return DB::table('xp_movimientos')
            ->where('id_estudiante', $idEstudiante)
            ->where('fuente', '<>', 'saldo_inicial')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn ($m) => [
                'tipo' => $m->fuente,
                'texto' => $m->descripcion,
                'tiempo' => date('d/m/Y H:i', strtotime($m->created_at)),
                'xp' => '+'.(int) $m->xp.' XP',
            ])
            ->all();
    }

    private function notificacionesData(int $idUsuario): array
    {
        return DB::table('notificaciones')
            ->where('id_usuario', $idUsuario)
            ->orderByDesc('created_at')
            ->orderByDesc('id_notificacion')
            ->limit(15)
            ->get()
            ->map(fn ($n) => [
                'id' => (int) $n->id_notificacion,
                'tipo' => $n->tipo,
                'texto' => $n->titulo.': '.$n->mensaje,
                'tiempo' => $n->created_at ? date('d/m/Y H:i', strtotime($n->created_at)) : '',
                'leida' => (bool) $n->leida_at,
            ])
            ->all();
    }

    private function tipoPractica(?string $categoria): string
    {
        return match (mb_strtolower((string) $categoria)) {
            'ritmo' => 'ritmo',
            'teoria' => 'teoria',
            'afinacion' => 'afinacion',
            'escala', 'tecnica' => 'tecnica',
            default => 'libre',
        };
    }

    private function urgenciaTarea(?string $fechaLimite, ?string $estado): string
    {
        if (in_array($estado, ['entregada', 'calificada'], true) || ! $fechaLimite) {
            return 'normal';
        }

        $dias = now()->startOfDay()->diffInDays(date_create($fechaLimite), false);

        return $dias < 0 ? 'vencida' : ($dias <= 1 ? 'urgente' : ($dias <= 4 ? 'normal' : 'proxima'));
    }
}
