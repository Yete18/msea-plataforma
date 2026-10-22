<?php

namespace App\Services;

use App\Models\EntregaTarea;
use App\Models\Notificacion;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reglas de tareas y entregas.
 *
 * Estados de una entrega:
 *   entregada  -> el estudiante envio su trabajo (puede reemplazarlo mientras no se califique)
 *   devuelta   -> el profesor la devolvio para correccion (el estudiante puede reentregar)
 *   calificada -> tiene nota; ya no se puede reentregar. El XP se otorga una sola vez.
 */
class TareaService
{
    public const ESTADOS_ENTREGA = ['entregada', 'devuelta', 'calificada'];

    public function __construct(
        private readonly GamificationService $gamificacion,
        private readonly ArchivoPrivadoService $archivos,
        private readonly EscalaService $escalas,
        private readonly TecnicaService $tecnica,
    ) {}

    // ---------------------------------------------------------------------
    // Visibilidad (S1)
    // ---------------------------------------------------------------------

    /**
     * Tareas que un estudiante puede ver: asignadas a el, o a SU elenco por
     * SU profesor. Una tarea de elenco llega solo a los alumnos del profesor
     * que la creo (regla de la fundacion: cada profesor ve y califica a sus
     * propios alumnos dentro del elenco). Si no tiene elenco o profesor, la
     * segunda condicion no se agrega (antes un orWhere('id_elenco', null) se
     * convertia en "IS NULL" y abria TODAS las tareas individuales de otros).
     */
    public function tareasVisiblesPara(object $estudiante): Builder
    {
        $idEstudiante = (int) $estudiante->id_estudiante;
        $idElenco = $estudiante->id_elenco ? (int) $estudiante->id_elenco : null;
        $idProfesor = ! empty($estudiante->id_profesor) ? (int) $estudiante->id_profesor : null;

        return DB::table('tareas as t')->where(function (Builder $q) use ($idEstudiante, $idElenco, $idProfesor) {
            $q->where('t.id_estudiante', $idEstudiante);

            if ($idElenco !== null && $idProfesor !== null) {
                $q->orWhere(function (Builder $q2) use ($idElenco, $idProfesor) {
                    $q2->whereNotNull('t.id_elenco')->where('t.id_elenco', $idElenco)->where('t.id_profesor', $idProfesor);
                });
            }
        });
    }

    public function tareaParaEstudiante(int $idTarea, object $estudiante): ?object
    {
        return $this->tareasVisiblesPara($estudiante)->where('t.id_tarea', $idTarea)->select('t.*')->first();
    }

    public function estudianteDeUsuario(int $idUsuario): ?object
    {
        return DB::table('estudiantes')->where('id_usuario', $idUsuario)->first();
    }

    public function profesorDeUsuario(int $idUsuario): ?object
    {
        return DB::table('profesores')->where('id_usuario', $idUsuario)->first();
    }

    // ---------------------------------------------------------------------
    // Estudiante: entregar
    // ---------------------------------------------------------------------

    public function entregar(object $estudiante, int $idTarea, ?string $comentario, ?UploadedFile $archivo): EntregaTarea
    {
        $tarea = $this->tareaParaEstudiante($idTarea, $estudiante);

        if (! $tarea) {
            throw new NotFoundHttpException('No se encontro la tarea o no tienes permiso para entregarla');
        }

        $previa = EntregaTarea::query()
            ->where('id_tarea', $idTarea)
            ->where('id_estudiante', $estudiante->id_estudiante)
            ->first();

        if ($previa && $previa->estado === 'calificada') {
            throw ValidationException::withMessages(['tarea' => 'Esta tarea ya fue calificada y no se puede volver a entregar.']);
        }

        if (! $archivo && ! trim((string) $comentario) && ! $previa?->archivo_path) {
            throw ValidationException::withMessages(['archivo' => 'Adjunta tu grabacion o escribe un comentario para entregar.']);
        }

        $nuevoArchivo = $archivo ? $this->archivos->guardarAudioEntrega($archivo) : null;
        $archivoViejo = $nuevoArchivo ? $previa?->archivo_path : null;

        try {
            $entrega = DB::transaction(function () use ($tarea, $estudiante, $comentario, $nuevoArchivo, $previa) {
                $entrega = $previa ?? new EntregaTarea([
                    'id_tarea' => $tarea->id_tarea,
                    'id_estudiante' => $estudiante->id_estudiante,
                ]);

                $entrega->comentario_estudiante = $comentario !== null ? trim($comentario) : $entrega->comentario_estudiante;
                $entrega->estado = 'entregada';
                $entrega->entregado_at = now();

                if ($nuevoArchivo) {
                    $entrega->archivo_path = $nuevoArchivo['path'];
                    $entrega->archivo_nombre = $nuevoArchivo['nombre'];
                    $entrega->archivo_mime = $nuevoArchivo['mime'];
                    $entrega->archivo_tamano = $nuevoArchivo['tamano'];
                    $entrega->archivo = $nuevoArchivo['nombre'];
                }

                $entrega->save();

                if ($tarea->id_estudiante) {
                    DB::table('tareas')->where('id_tarea', $tarea->id_tarea)->update(['estado' => 'entregada']);
                }

                $idUsuarioProfesor = DB::table('profesores')->where('id_profesor', $tarea->id_profesor)->value('id_usuario');
                if ($idUsuarioProfesor) {
                    $alumno = DB::table('usuarios')->where('id_usuario', $estudiante->id_usuario)->first(['nombres', 'apellido_paterno']);
                    Notificacion::query()->create([
                        'id_usuario' => $idUsuarioProfesor,
                        'titulo' => $previa ? 'Entrega actualizada' : 'Nueva entrega de tarea',
                        'mensaje' => trim(($alumno->nombres ?? '').' '.($alumno->apellido_paterno ?? '')).' entrego: '.$tarea->titulo,
                        'tipo' => 'tarea',
                    ]);
                }

                return $entrega;
            });
        } catch (\Throwable $e) {
            $this->archivos->eliminar($nuevoArchivo['path'] ?? null);
            throw $e;
        }

        $this->archivos->eliminar($archivoViejo);

        return $entrega;
    }

    // ---------------------------------------------------------------------
    // Profesor: CRUD de tareas
    // ---------------------------------------------------------------------

    public function crearTarea(object $profesor, array $datos, ?UploadedFile $material): object
    {
        [$idEstudiante, $idElenco] = $this->resolverAsignacion($profesor, $datos);
        $datos = $this->prepararEscala($datos);
        $archivo = $material ? $this->archivos->guardarMaterial($material) : null;

        try {
            return DB::transaction(function () use ($profesor, $datos, $idEstudiante, $idElenco, $archivo) {
                $idTipo = DB::table('tipos_ejercicio')->where('nombre', $datos['tipo'] ?? 'Repertorio')->value('id_tipo')
                    ?: DB::table('tipos_ejercicio')->where('nombre', 'Repertorio')->value('id_tipo')
                    ?: DB::table('tipos_ejercicio')->insertGetId(['nombre' => 'Repertorio'], 'id_tipo');

                $xp = (int) ($datos['xp_recompensa'] ?? 30);

                $idEjercicio = DB::table('ejercicios')->insertGetId([
                    'id_tipo' => $idTipo,
                    'titulo' => $datos['titulo'],
                    'descripcion' => $datos['descripcion'] ?? null,
                    'creado_por' => $profesor->id_profesor,
                    'dificultad' => $datos['_dificultad'] ?? 'basico',
                    'xp' => $xp,
                ], 'id_ejercicio');

                $idTarea = DB::table('tareas')->insertGetId([
                    'titulo' => $datos['titulo'],
                    'descripcion' => $datos['descripcion'] ?? null,
                    'fecha_creacion' => now(),
                    'id_profesor' => $profesor->id_profesor,
                    'id_estudiante' => $idEstudiante,
                    'id_elenco' => $idElenco,
                    'fecha_limite' => $datos['fecha_limite'] ?? null,
                    'xp_recompensa' => $xp,
                    'estado' => 'pendiente',
                    'material_path' => $archivo['path'] ?? null,
                    'material_nombre' => $archivo['nombre'] ?? null,
                    'material_mime' => $archivo['mime'] ?? null,
                    'material_tamano' => $archivo['tamano'] ?? null,
                    'id_biblioteca' => $datos['_id_biblioteca'] ?? null,
                    'config_escala' => isset($datos['_config_escala']) ? json_encode($datos['_config_escala']) : null,
                ], 'id_tarea');

                DB::table('tarea_ejercicio')->insert(['id_tarea' => $idTarea, 'id_ejercicio' => $idEjercicio]);

                $this->notificarAsignados($idEstudiante, $idElenco, (int) $profesor->id_profesor, 'Nueva tarea asignada', $datos['titulo']);

                return DB::table('tareas')->where('id_tarea', $idTarea)->first();
            });
        } catch (\Throwable $e) {
            $this->archivos->eliminar($archivo['path'] ?? null);
            throw $e;
        }
    }

    public function actualizarTarea(object $profesor, int $idTarea, array $datos, ?UploadedFile $material): object
    {
        $tarea = $this->tareaDelProfesor($profesor, $idTarea);
        $tieneEntregas = DB::table('entregas_tareas')->where('id_tarea', $idTarea)->exists();

        if ($tarea->id_biblioteca && ! empty($datos['config_escala'])) {
            $datos['id_biblioteca'] = $datos['id_biblioteca'] ?? $tarea->id_biblioteca;
        }
        $datos = $this->prepararEscala($datos, $tarea);

        $cambios = [
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'] ?? null,
            'fecha_limite' => $datos['fecha_limite'] ?? null,
            'xp_recompensa' => (int) ($datos['xp_recompensa'] ?? $tarea->xp_recompensa),
        ];
        if (isset($datos['_config_escala'])) {
            $cambios['id_biblioteca'] = $datos['_id_biblioteca'];
            $cambios['config_escala'] = json_encode($datos['_config_escala']);
        }

        if (! empty($datos['id_estudiante']) || ! empty($datos['id_elenco'])) {
            [$idEstudiante, $idElenco] = $this->resolverAsignacion($profesor, $datos);
            if ($tieneEntregas && ((int) $idEstudiante !== (int) $tarea->id_estudiante || (int) $idElenco !== (int) $tarea->id_elenco)) {
                throw ValidationException::withMessages(['asignacion' => 'No puedes cambiar a quién está asignada una tarea que ya tiene entregas.']);
            }
            $cambios['id_estudiante'] = $idEstudiante;
            $cambios['id_elenco'] = $idElenco;
        }

        $archivo = $material ? $this->archivos->guardarMaterial($material) : null;
        if ($archivo) {
            $cambios += [
                'material_path' => $archivo['path'],
                'material_nombre' => $archivo['nombre'],
                'material_mime' => $archivo['mime'],
                'material_tamano' => $archivo['tamano'],
            ];
        }

        try {
            DB::transaction(fn () => DB::table('tareas')->where('id_tarea', $idTarea)->update($cambios));
        } catch (\Throwable $e) {
            // Si no se guardo, no se deja el material nuevo huerfano en disco.
            if ($archivo) {
                $this->archivos->eliminar($archivo['path']);
            }
            throw $e;
        }

        if ($archivo) {
            $this->archivos->eliminar($tarea->material_path);
        }

        return DB::table('tareas')->where('id_tarea', $idTarea)->first();
    }

    public function eliminarTarea(object $profesor, int $idTarea): void
    {
        $tarea = $this->tareaDelProfesor($profesor, $idTarea);

        // Cualquier entrega (entregada, devuelta o calificada) es trabajo del
        // estudiante: la tarea se conserva para no borrar sus grabaciones.
        if (DB::table('entregas_tareas')->where('id_tarea', $idTarea)->exists()) {
            throw ValidationException::withMessages(['tarea' => 'La tarea ya tiene entregas de estudiantes; se conserva para no perder su trabajo.']);
        }

        DB::transaction(function () use ($idTarea) {
            $ejercicios = DB::table('tarea_ejercicio')->where('id_tarea', $idTarea)->pluck('id_ejercicio');
            DB::table('tareas')->where('id_tarea', $idTarea)->delete();
            // Ejercicios creados solo para esta tarea y sin otro uso.
            foreach ($ejercicios as $idEjercicio) {
                $usado = DB::table('tarea_ejercicio')->where('id_ejercicio', $idEjercicio)->exists()
                    || DB::table('progreso')->where('id_ejercicio', $idEjercicio)->exists();
                if (! $usado) {
                    DB::table('ejercicios')->where('id_ejercicio', $idEjercicio)->delete();
                }
            }
        });

        $this->archivos->eliminar($tarea->material_path);
    }

    public function entregasDeTarea(object $profesor, int $idTarea): array
    {
        $this->tareaDelProfesor($profesor, $idTarea);

        return DB::table('entregas_tareas as et')
            ->join('estudiantes as e', 'e.id_estudiante', '=', 'et.id_estudiante')
            ->join('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->where('et.id_tarea', $idTarea)
            ->where('e.id_profesor', $profesor->id_profesor)
            ->orderByDesc('et.entregado_at')
            ->get([
                'et.id_entrega', 'et.estado', 'et.comentario_estudiante', 'et.calificacion', 'et.comentario_profesor',
                'et.entregado_at', 'et.calificado_at', 'et.devuelta_at', 'et.archivo_nombre', 'et.archivo_path',
                'et.resultado_escala', 'u.nombres', 'u.apellido_paterno',
            ])
            ->map(fn ($e) => [
                'id_entrega' => (int) $e->id_entrega,
                'alumno' => trim($e->nombres.' '.$e->apellido_paterno),
                'estado' => $e->estado,
                'comentario_estudiante' => $e->comentario_estudiante,
                'calificacion' => $e->calificacion !== null ? (float) $e->calificacion : null,
                'comentario_profesor' => $e->comentario_profesor,
                'entregado_at' => $e->entregado_at,
                'tiene_archivo' => (bool) $e->archivo_path,
                'archivo_nombre' => $e->archivo_nombre,
                'archivo_url' => $e->archivo_path ? url('/archivos/entregas/'.$e->id_entrega) : null,
                'resultado_escala' => $e->resultado_escala ? $this->escalas->decodificar($e->resultado_escala) : null,
            ])
            ->all();
    }

    // ---------------------------------------------------------------------
    // Tareas de escala (biblioteca técnica)
    // ---------------------------------------------------------------------

    /**
     * Si la tarea usa una escala de la biblioteca, valida la configuración y
     * completa título, tipo y descripción. Las claves con "_" son internas.
     */
    private function prepararEscala(array $datos, ?object $tarea = null): array
    {
        if (empty($datos['id_biblioteca'])) {
            if (empty($datos['titulo'])) {
                throw ValidationException::withMessages(['titulo' => 'Escribe el título de la tarea.']);
            }

            return $datos;
        }

        $categoria = DB::table('biblioteca_ejercicios')->where('id_biblioteca', (int) $datos['id_biblioteca'])->value('categoria');
        if ($categoria && $categoria !== 'escala') {
            return $this->prepararTecnica($datos, $tarea);
        }

        $escala = $this->escalas->escala((int) $datos['id_biblioteca'], soloActivas: ! $tarea || (int) $tarea->id_biblioteca !== (int) $datos['id_biblioteca']);
        if (! $escala) {
            throw ValidationException::withMessages(['id_biblioteca' => 'La escala elegida no está disponible.']);
        }

        if ($tarea && (int) $tarea->id_biblioteca === (int) $escala->id_biblioteca
            && DB::table('entregas_tareas')->where('id_tarea', $tarea->id_tarea)->whereNotNull('resultado_escala')->exists()
            && $this->escalas->normalizarConfig($datos['config_escala'] ?? null, $escala) != $this->escalas->decodificar($tarea->config_escala)) {
            throw ValidationException::withMessages(['config_escala' => 'Ya hay estudiantes que tocaron esta escala; crea una tarea nueva para cambiar cómo se toca.']);
        }

        $config = $this->escalas->normalizarConfig($datos['config_escala'] ?? null, $escala);

        $datos['titulo'] = trim((string) ($datos['titulo'] ?? '')) ?: $this->escalas->titulo($escala, $config);
        $datos['tipo'] = 'Escala';
        $datos['descripcion'] = trim((string) ($datos['descripcion'] ?? '')) ?: null;
        $datos['_id_biblioteca'] = (int) $escala->id_biblioteca;
        $datos['_config_escala'] = $config;
        $datos['_dificultad'] = $escala->dificultad;

        return $datos;
    }

    /** Tarea con un ejercicio técnico o de un método digitalizado. */
    private function prepararTecnica(array $datos, ?object $tarea): array
    {
        $mismo = $tarea && (int) $tarea->id_biblioteca === (int) $datos['id_biblioteca'];
        $fila = $this->tecnica->ejercicio((int) $datos['id_biblioteca'], soloActivos: ! $mismo);
        if (! $fila) {
            throw ValidationException::withMessages(['id_biblioteca' => 'El ejercicio elegido no está disponible.']);
        }
        $config = $this->tecnica->normalizarConfig($datos['config_escala'] ?? null, $fila);

        $datos['titulo'] = trim((string) ($datos['titulo'] ?? '')) ?: $this->tecnica->titulo($fila, $config);
        $datos['tipo'] = 'Tecnica';
        $datos['descripcion'] = trim((string) ($datos['descripcion'] ?? '')) ?: null;
        $datos['_id_biblioteca'] = (int) $fila->id_biblioteca;
        $datos['_config_escala'] = $config;
        $datos['_dificultad'] = $fila->dificultad;

        return $datos;
    }

    /** Datos de la práctica guiada de una tarea de escala, si el estudiante puede verla. */
    public function ejercicioEscalaParaEstudiante(object $estudiante, int $idTarea, ?string $instrumentoElegido = null): array
    {
        $tarea = $this->tareaParaEstudiante($idTarea, $estudiante);
        if (! $tarea || ! $tarea->id_biblioteca) {
            throw new NotFoundHttpException('No se encontró la tarea de escala.');
        }
        $instrumentos = $this->escalas->instrumentosDeUsuario((int) $estudiante->id_usuario);
        $entrega = DB::table('entregas_tareas')->where('id_tarea', $idTarea)->where('id_estudiante', $estudiante->id_estudiante)->first();

        $tecnica = $this->tecnica->ejercicio((int) $tarea->id_biblioteca, soloActivos: false);
        if ($tecnica) {
            $config = $this->tecnica->normalizarConfig($tarea->config_escala, $tecnica);
            $instrumento = match (true) {
                $instrumentoElegido && in_array($instrumentoElegido, $instrumentos, true) => $instrumentoElegido,
                in_array($tecnica->instrumento, $instrumentos, true) => $tecnica->instrumento,
                (bool) $instrumentos => $instrumentos[0],
                default => $tecnica->instrumento,
            };
            $fila = $this->tecnica->paraInstrumento($tecnica, $instrumento) ?? $tecnica;

            return ['tarea' => $tarea, 'entrega' => $entrega, 'instrumentos' => $tecnica->categoria === 'metodo' ? [] : $instrumentos, 'ejercicio' => $this->tecnica->ejercicioPractica($fila, $config)];
        }

        $escala = $this->escalas->escala((int) $tarea->id_biblioteca, soloActivas: false);
        if (! $escala) {
            throw new NotFoundHttpException('El ejercicio de esta tarea ya no existe.');
        }

        $config = $this->escalas->normalizarConfig($tarea->config_escala, $escala);

        // La escala se toca en el instrumento del estudiante (clave, registro y digitación propios).
        // Si toca más de uno, puede elegir; si no tiene instrumento registrado, se usa el de la tarea.
        $instrumento = match (true) {
            $instrumentoElegido && in_array($instrumentoElegido, $instrumentos, true) => $instrumentoElegido,
            in_array($escala->instrumento, $instrumentos, true) => $escala->instrumento,
            (bool) $instrumentos => $instrumentos[0],
            default => $escala->instrumento,
        };
        $escalaInstrumento = $this->escalas->escalaParaInstrumento($escala, $instrumento) ?? $escala;

        return [
            'tarea' => $tarea,
            'entrega' => $entrega,
            'instrumentos' => $instrumentos,
            'ejercicio' => $this->escalas->ejercicio($escalaInstrumento, $config),
        ];
    }

    /** Guarda el resultado de la escala y lo entrega al profesor con un resumen. */
    public function entregarEscala(object $estudiante, int $idTarea, array $resultado, ?string $comentario, ?string $instrumento = null): array
    {
        $info = $this->ejercicioEscalaParaEstudiante($estudiante, $idTarea, $instrumento);
        $limpio = $this->escalas->normalizarResultado($resultado, count($info['ejercicio']['notas']));
        $limpio['instrumento'] = $info['ejercicio']['instrumento']['clave'];
        $limpio['octavas'] = $info['ejercicio']['config']['octavas'] ?? null;

        if (! $limpio['completada']) {
            throw ValidationException::withMessages(['resultado' => 'Termina la escala completa antes de entregarla.']);
        }

        $texto = $this->escalas->textoResultado($limpio);
        $comentario = trim((string) $comentario);
        $entrega = $this->entregar($estudiante, $idTarea, $comentario !== '' ? $comentario."\n\n".$texto : $texto, null);

        DB::table('entregas_tareas')->where('id_entrega', $entrega->id_entrega)->update(['resultado_escala' => json_encode($limpio)]);

        return ['entrega' => $entrega->fresh(), 'resultado' => $limpio, 'texto' => $texto];
    }

    // ---------------------------------------------------------------------
    // Profesor: calificar y devolver (L3)
    // ---------------------------------------------------------------------

    /**
     * Califica (o corrige la nota). El XP de la tarea se otorga solo la
     * primera vez, con la clave "tarea:{id_entrega}".
     */
    public function calificar(object $profesor, int $idEntrega, float $calificacion, ?string $comentario): array
    {
        $entrega = $this->entregaDelProfesor($profesor, $idEntrega);

        if (! in_array($entrega->estado, ['entregada', 'calificada'], true)) {
            throw ValidationException::withMessages(['entrega' => 'Solo se pueden calificar entregas recibidas.']);
        }

        $xpOtorgado = DB::transaction(function () use ($entrega, $calificacion, $comentario) {
            DB::table('entregas_tareas')->where('id_entrega', $entrega->id_entrega)->update([
                'calificacion' => $calificacion,
                'comentario_profesor' => $comentario,
                'estado' => 'calificada',
                'calificado_at' => now(),
                'updated_at' => now(),
            ]);

            if ($entrega->tarea_id_estudiante) {
                DB::table('tareas')->where('id_tarea', $entrega->id_tarea)->update(['estado' => 'calificada']);
            }

            $xp = $this->gamificacion->otorgarXp(
                (int) $entrega->id_estudiante,
                (int) $entrega->xp_recompensa,
                'tarea',
                'tarea:'.$entrega->id_entrega,
                'Tarea calificada: '.$entrega->titulo
            );

            $this->notificarEstudiante((int) $entrega->id_estudiante, 'Tarea calificada',
                'Tu tarea "'.$entrega->titulo.'" fue calificada con '.(int) round($calificacion).'/100.');

            return $xp;
        });

        return ['xp_otorgado' => $xpOtorgado, 'recalificacion' => $entrega->estado === 'calificada'];
    }

    public function devolver(object $profesor, int $idEntrega, string $comentario): void
    {
        $entrega = $this->entregaDelProfesor($profesor, $idEntrega);

        if ($entrega->estado !== 'entregada') {
            throw ValidationException::withMessages(['entrega' => 'Solo se pueden devolver entregas pendientes de calificar.']);
        }

        DB::transaction(function () use ($entrega, $comentario) {
            DB::table('entregas_tareas')->where('id_entrega', $entrega->id_entrega)->update([
                'estado' => 'devuelta',
                'comentario_profesor' => $comentario,
                'devuelta_at' => now(),
                'updated_at' => now(),
            ]);

            if ($entrega->tarea_id_estudiante) {
                DB::table('tareas')->where('id_tarea', $entrega->id_tarea)->update(['estado' => 'devuelta']);
            }

            $this->notificarEstudiante((int) $entrega->id_estudiante, 'Tarea devuelta para corregir',
                '"'.$entrega->titulo.'": '.$comentario);
        });
    }

    // ---------------------------------------------------------------------
    // Permisos sobre archivos
    // ---------------------------------------------------------------------

    public function puedeVerEntrega(int $idUsuario, string $rol, object $entrega): bool
    {
        return match ($rol) {
            'director' => true,
            'estudiante' => (int) DB::table('estudiantes')->where('id_usuario', $idUsuario)->value('id_estudiante') === (int) $entrega->id_estudiante,
            // La tarea es suya Y el alumno es suyo (tareas de elenco compartidas).
            'profesor' => DB::table('tareas as t')->join('profesores as p', 'p.id_profesor', '=', 't.id_profesor')
                ->join('estudiantes as e', 'e.id_profesor', '=', 'p.id_profesor')
                ->where('t.id_tarea', $entrega->id_tarea)->where('e.id_estudiante', $entrega->id_estudiante)
                ->where('p.id_usuario', $idUsuario)->exists(),
            'padre' => $this->padreVinculado($idUsuario, (int) $entrega->id_estudiante),
            default => false,
        };
    }

    public function puedeVerMaterial(int $idUsuario, string $rol, object $tarea): bool
    {
        if ($rol === 'director') {
            return true;
        }

        if ($rol === 'profesor') {
            return (int) DB::table('profesores')->where('id_usuario', $idUsuario)->value('id_profesor') === (int) $tarea->id_profesor;
        }

        $estudiantes = match ($rol) {
            'estudiante' => DB::table('estudiantes')->where('id_usuario', $idUsuario)->get(),
            'padre' => DB::table('estudiante_padre as ep')->join('padres as p', 'p.id_padre', '=', 'ep.id_padre')
                ->join('estudiantes as e', 'e.id_estudiante', '=', 'ep.id_estudiante')
                ->where('p.id_usuario', $idUsuario)->select('e.*')->get(),
            default => collect(),
        };

        return $estudiantes->contains(fn ($e) => (bool) $this->tareaParaEstudiante((int) $tarea->id_tarea, $e));
    }

    public function padreVinculado(int $idUsuarioPadre, int $idEstudiante): bool
    {
        return DB::table('estudiante_padre as ep')
            ->join('padres as p', 'p.id_padre', '=', 'ep.id_padre')
            ->where('p.id_usuario', $idUsuarioPadre)
            ->where('ep.id_estudiante', $idEstudiante)
            ->exists();
    }

    // ---------------------------------------------------------------------

    /**
     * Valida que la tarea vaya a UN estudiante del profesor o a UN elenco
     * donde el profesor tenga al menos un estudiante.
     */
    private function resolverAsignacion(object $profesor, array $datos): array
    {
        $idEstudiante = ! empty($datos['id_estudiante']) ? (int) $datos['id_estudiante'] : null;
        $idElenco = ! empty($datos['id_elenco']) ? (int) $datos['id_elenco'] : null;

        if (($idEstudiante === null) === ($idElenco === null)) {
            throw ValidationException::withMessages(['asignacion' => 'Asigna la tarea a un estudiante o a un elenco (solo uno).']);
        }

        if ($idEstudiante !== null) {
            $propio = DB::table('estudiantes')->where('id_estudiante', $idEstudiante)->where('id_profesor', $profesor->id_profesor)->exists();
            if (! $propio) {
                throw ValidationException::withMessages(['id_estudiante' => 'El estudiante no está asignado a este profesor']);
            }
        } else {
            $tieneAlumnos = DB::table('estudiantes')->where('id_elenco', $idElenco)->where('id_profesor', $profesor->id_profesor)->exists();
            if (! $tieneAlumnos) {
                throw ValidationException::withMessages(['id_elenco' => 'No tienes estudiantes en ese elenco']);
            }
        }

        return [$idEstudiante, $idElenco];
    }

    private function tareaDelProfesor(object $profesor, int $idTarea): object
    {
        $tarea = DB::table('tareas')->where('id_tarea', $idTarea)->where('id_profesor', $profesor->id_profesor)->first();

        if (! $tarea) {
            throw new NotFoundHttpException('No se encontro la tarea o no te pertenece');
        }

        return $tarea;
    }

    private function entregaDelProfesor(object $profesor, int $idEntrega): object
    {
        $entrega = DB::table('entregas_tareas as et')
            ->join('tareas as t', 't.id_tarea', '=', 'et.id_tarea')
            ->join('estudiantes as e', 'e.id_estudiante', '=', 'et.id_estudiante')
            ->where('et.id_entrega', $idEntrega)
            ->where('t.id_profesor', $profesor->id_profesor)
            ->where('e.id_profesor', $profesor->id_profesor)
            ->select('et.*', 't.titulo', 't.xp_recompensa', 't.id_estudiante as tarea_id_estudiante')
            ->first();

        if (! $entrega) {
            throw new NotFoundHttpException('No se encontro la entrega o no tienes permiso para calificarla');
        }

        return $entrega;
    }

    private function notificarAsignados(?int $idEstudiante, ?int $idElenco, int $idProfesor, string $titulo, string $mensaje): void
    {
        $ids = $idEstudiante
            ? DB::table('estudiantes')->where('id_estudiante', $idEstudiante)->pluck('id_usuario')
            : DB::table('estudiantes')->where('id_elenco', $idElenco)->where('id_profesor', $idProfesor)->pluck('id_usuario');

        foreach ($ids as $idUsuario) {
            Notificacion::query()->create(['id_usuario' => $idUsuario, 'titulo' => $titulo, 'mensaje' => $mensaje, 'tipo' => 'tarea']);
        }
    }

    private function notificarEstudiante(int $idEstudiante, string $titulo, string $mensaje): void
    {
        $idUsuario = DB::table('estudiantes')->where('id_estudiante', $idEstudiante)->value('id_usuario');
        if ($idUsuario) {
            Notificacion::query()->create(['id_usuario' => $idUsuario, 'titulo' => $titulo, 'mensaje' => mb_substr($mensaje, 0, 1000), 'tipo' => 'tarea']);
        }
    }
}
