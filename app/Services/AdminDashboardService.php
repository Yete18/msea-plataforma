<?php

namespace App\Services;

use App\Models\Elenco;
use App\Models\Estudiante;
use App\Models\Instrumento;
use App\Models\Profesor;
use App\Models\Rol;
use App\Models\TipoElenco;
use App\Models\Usuario;
use App\Models\UsuarioInstrumento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Datos y acciones del panel del director.
 *
 * El esquema ya es estable (migraciones), por eso aqui no se consulta
 * Schema::hasTable/hasColumn en cada peticion. Las consultas pesadas se
 * memorizan durante la peticion para no repetirlas.
 */
class AdminDashboardService
{
    private array $memo = [];

    /** Centralizador (UX-08): pulso, elencos y asignaciones, actividad y alarmas, reportes. */
    public function viewData(int $idUsuario): array
    {
        $porElenco = collect($this->estudiantesData())->groupBy('id_elenco');

        return [
            'adminData' => $this->adminData($idUsuario),
            'profesoresData' => $this->profesoresData(),
            'elencosData' => collect($this->elencosData())
                ->map(fn ($el) => $el + ['alumnos' => ($porElenco[$el['id']] ?? collect())->values()->all()])
                ->all(),
            'estudiantesData' => $this->estudiantesData(),
            'actividadData' => $this->actividadData(),
            'reportesData' => $this->reportesData(),
            'alarmasData' => $this->alarmasData(),
            'pulsoData' => $this->pulsoData(),
        ];
    }

    /** Pagina de profesores: listado con sus alumnos y alta de profesores. */
    public function profesoresPagina(): array
    {
        return [
            'profesoresData' => $this->profesoresData(),
            'instrumentos' => DB::table('instrumentos')->orderBy('nombre')->get(['id_instrumento', 'nombre']),
        ];
    }

    /** Pagina de perfil del director, con la guia rapida y las cuentas por rol. */
    public function perfilPagina(int $idUsuario): array
    {
        return [
            'directorProfile' => $this->directorProfileData($idUsuario),
            'ayudaData' => $this->ayudaData(),
            'cuentasPorRol' => $this->cuentasPorRol(),
        ];
    }

    /**
     * Pulso de la academia: indicadores accionables de los ultimos 7 dias y
     * pendientes concretos del director (con enlace a donde se resuelven).
     */
    public function pulsoData(): array
    {
        $desde = now()->subDays(6)->startOfDay();
        $activos = DB::table('estudiantes as e')->join('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')->where('u.activo', true);
        $totalActivos = (clone $activos)->count();
        $practicaron = DB::table('practicas_autonomas as pa')
            ->join('estudiantes as e', 'e.id_estudiante', '=', 'pa.id_estudiante')
            ->join('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->where('u.activo', true)
            ->where('pa.estado', 'finalizada')
            ->where('pa.created_at', '>=', $desde)
            ->distinct()->count('pa.id_estudiante');
        $minutos = (int) round(DB::table('practicas_autonomas')->where('estado', 'finalizada')->where('created_at', '>=', $desde)->sum('duracion_segundos') / 60);
        $porRevisar = DB::table('entregas_tareas')->where('estado', 'entregada')->count();
        $atrasadas = DB::table('entregas_tareas')->where('estado', 'entregada')
            ->where(DB::raw('coalesce(entregado_at, created_at)'), '<', now()->subDays(3))->count();

        $sinProfesor = (clone $activos)->whereNull('e.id_profesor')->count();
        $sinElenco = (clone $activos)->whereNull('e.id_elenco')->count();
        $sinFamilia = (clone $activos)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('estudiante_padre as ep')->whereColumn('ep.id_estudiante', 'e.id_estudiante'))->count();
        $nuncaPracticaron = (clone $activos)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('practicas_autonomas as pa')->whereColumn('pa.id_estudiante', 'e.id_estudiante')->where('pa.estado', 'finalizada'))->count();

        $pendientes = array_values(array_filter([
            $sinProfesor ? ['icono' => 'perfil', 'texto' => "$sinProfesor estudiante(s) sin profesor asignado", 'url' => url('/dashboard-admin#asignaciones'), 'nivel' => 'alto'] : null,
            $atrasadas ? ['icono' => 'reloj', 'texto' => "$atrasadas entrega(s) esperan revisión hace más de 3 días", 'url' => url('/dashboard-admin/profesores'), 'nivel' => 'alto'] : null,
            $sinElenco ? ['icono' => 'grupo', 'texto' => "$sinElenco estudiante(s) sin elenco u orquesta", 'url' => url('/dashboard-admin#asignaciones'), 'nivel' => 'medio'] : null,
            $sinFamilia ? ['icono' => 'usuarios', 'texto' => "$sinFamilia estudiante(s) sin familia vinculada", 'url' => url('/dashboard-admin/usuarios#padres'), 'nivel' => 'medio'] : null,
            $nuncaPracticaron ? ['icono' => 'musica', 'texto' => "$nuncaPracticaron estudiante(s) aún no registran prácticas", 'url' => url('/dashboard-admin/usuarios'), 'nivel' => 'bajo'] : null,
        ]));

        return [
            'estudiantesActivos' => $totalActivos,
            'practicaronSemana' => $practicaron,
            'porcentajeActivos' => $totalActivos ? (int) round($practicaron * 100 / $totalActivos) : 0,
            'minutosSemana' => $minutos,
            'entregasPorRevisar' => $porRevisar,
            'pendientes' => $pendientes,
        ];
    }

    public function reportData(string $tipo): ?array
    {
        return match ($tipo) {
            'estudiantes' => [
                'filename' => 'reporte_estudiantes.csv',
                'headers' => ['ID', 'Nombre', 'Correo', 'Elenco', 'Profesor', 'Nivel MSEA', 'XP', 'Estado'],
                'rows' => collect($this->estudiantesData())->map(fn ($e) => [
                    $e['id'], $e['nombre'], $e['correo'], $e['elenco'], $e['profesor'], $e['nivel'], $e['xp'], $e['activo'] ? 'Activo' : 'Inactivo',
                ])->all(),
            ],
            'profesores' => [
                'filename' => 'reporte_profesores.csv',
                'headers' => ['ID', 'Nombre', 'Correo', 'Especialidad', 'Celular', 'Alumnos', 'Estado'],
                'rows' => collect($this->profesoresData())->map(fn ($p) => [
                    $p['id'], $p['nombre'], $p['correo'], $p['especialidad'], $p['celular'], $p['estudiantes'], $p['activo'] ? 'Activo' : 'Inactivo',
                ])->all(),
            ],
            'actividad' => [
                'filename' => 'reporte_actividad.csv',
                'headers' => ['Tipo', 'Titulo', 'Detalle', 'Fecha'],
                'rows' => collect($this->actividadData()['items'])->map(fn ($a) => [$a['tipo'], $a['titulo'], $a['detalle'], $a['fecha']])->all(),
            ],
            'ranking' => [
                'filename' => 'reporte_ranking.csv',
                'headers' => ['Posicion', 'Estudiante', 'XP'],
                'rows' => DB::table('ranking_estudiantes')->orderBy('posicion')->get()
                    ->map(fn ($r) => [(int) $r->posicion, trim($r->nombres.' '.$r->apellido_paterno), (int) $r->puntaje_total])->all(),
            ],
            default => null,
        };
    }

    public function updateProfile(int $idUsuario, array $validated): bool
    {
        $payload = [
            'nombres' => $validated['nombres'],
            'apellido_paterno' => $validated['apellido_paterno'] ?? null,
            'apellido_materno' => $validated['apellido_materno'] ?? null,
            'correo' => AuthService::normalizarCorreo($validated['correo']),
            'ci' => $validated['ci'] ?? null,
            'celular' => $validated['celular'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'fecha_nacimiento' => $validated['fecha_nacimiento'] ?? null,
            'trayectoria' => $validated['trayectoria'] ?? null,
        ];

        Usuario::query()->whereKey($idUsuario)->update($payload);

        session([
            'nombre' => $payload['nombres'],
            'apellido_paterno' => $payload['apellido_paterno'],
            'correo' => $payload['correo'],
        ]);

        return true;
    }

    public function storeProfesor(array $validated): bool
    {
        $rolProfesor = Rol::query()->where('nombre', 'profesor')->first();

        if (! $rolProfesor) {
            return false;
        }

        DB::transaction(function () use ($validated, $rolProfesor) {
            $usuario = Usuario::query()->create([
                'correo' => AuthService::normalizarCorreo($validated['correo']),
                'contrasena' => Hash::make($validated['contrasena']),
                'nombres' => $validated['nombres'],
                'apellido_paterno' => $validated['apellido_paterno'],
                'apellido_materno' => $validated['apellido_materno'] ?? null,
                'ci' => $validated['ci'] ?? null,
                'celular' => $validated['celular'] ?? null,
                'direccion' => $validated['direccion'] ?? null,
                'fecha_nacimiento' => $validated['fecha_nacimiento'] ?? null,
                'id_rol' => $rolProfesor->id_rol,
            ]);

            Profesor::query()->create(['id_usuario' => $usuario->id_usuario]);

            if (! empty($validated['especialidad'])) {
                UsuarioInstrumento::query()->firstOrCreate([
                    'id_usuario' => $usuario->id_usuario,
                    'id_instrumento' => Instrumento::query()->firstOrCreate(['nombre' => AuthService::normalizarInstrumento($validated['especialidad'])])->id_instrumento,
                ]);
            }
        });

        return true;
    }

    public function storeElenco(array $validated): string|true
    {
        DB::transaction(function () use ($validated) {
            Elenco::query()->firstOrCreate([
                'nombre' => $validated['nombre'],
                'id_tipo' => TipoElenco::query()->firstOrCreate(['nombre' => $validated['tipo']])->id_tipo,
            ]);
        });

        return true;
    }

    public function updateElenco(int $idElenco, array $validated): void
    {
        Elenco::query()->whereKey($idElenco)->update([
            'nombre' => $validated['nombre'],
            'id_tipo' => TipoElenco::query()->firstOrCreate(['nombre' => $validated['tipo']])->id_tipo,
        ]);
    }

    /**
     * Elimina un elenco sin tareas. Si tiene tareas (aunque sean antiguas) se
     * conserva: borrarlo dejaria esas tareas sin destinatario y sus entregas
     * sin contexto (en PostgreSQL ademas lo impide chk_asignacion_tarea).
     */
    public function destroyElenco(int $idElenco): void
    {
        $tareas = DB::table('tareas')->where('id_elenco', $idElenco)->count();
        if ($tareas > 0) {
            throw ValidationException::withMessages([
                'elenco' => "No se puede eliminar: el elenco tiene {$tareas} tarea(s) en su historial. Cámbiale el nombre o deja de asignarle estudiantes.",
            ]);
        }

        DB::transaction(function () use ($idElenco) {
            Estudiante::query()->where('id_elenco', $idElenco)->update(['id_elenco' => null]);
            Elenco::query()->whereKey($idElenco)->delete();
        });
    }

    public function assignStudentToElenco(int $idEstudiante, ?int $idElenco): string|true
    {
        Estudiante::query()->whereKey($idEstudiante)->update(['id_elenco' => $idElenco]);

        return true;
    }

    public function assignStudentToProfesor(int $idEstudiante, ?int $idProfesor): string|true
    {
        Estudiante::query()->whereKey($idEstudiante)->update(['id_profesor' => $idProfesor]);

        return true;
    }

    // ---------------------------------------------------------------------

    private function adminData(int $idUsuario): array
    {
        $usuario = Usuario::query()->whereKey($idUsuario)->first();

        return [
            'nombre' => $usuario ? trim($usuario->nombres.' '.$usuario->apellido_paterno) : 'Director',
            'email' => $usuario->correo ?? 'Sin correo',
            'totalProfesores' => DB::table('profesores')->count(),
            'totalEstudiantes' => DB::table('estudiantes')->count(),
            'totalElencos' => DB::table('elencos')->count(),
            'totalTareas' => DB::table('tareas')->count(),
            'totalEjercicios' => DB::table('ejercicios')->count(),
            'totalPracticas' => DB::table('practicas_autonomas')->where('estado', 'finalizada')->count(),
            'totalCursos' => DB::table('cursos')->where('activo', true)->count(),
            'totalPadres' => DB::table('padres')->count(),
        ];
    }

    public function profesoresData(): array
    {
        return $this->memo['profesores'] ??= (function () {
            $estudiantesPorProfesor = collect($this->estudiantesData())->groupBy('id_profesor');
            $instrumentosSql = DB::connection()->getDriverName() === 'pgsql'
                ? "coalesce(string_agg(distinct i.nombre, ', '), 'Sin especialidad') as especialidad"
                : "coalesce(group_concat(distinct i.nombre), 'Sin especialidad') as especialidad";

            return DB::table('profesores as p')
                ->join('usuarios as u', 'u.id_usuario', '=', 'p.id_usuario')
                ->leftJoin('usuario_instrumento as ui', 'ui.id_usuario', '=', 'u.id_usuario')
                ->leftJoin('instrumentos as i', 'i.id_instrumento', '=', 'ui.id_instrumento')
                ->leftJoin('estudiantes as e', 'e.id_profesor', '=', 'p.id_profesor')
                ->select([
                    'p.id_profesor', 'u.id_usuario', 'u.nombres', 'u.apellido_paterno', 'u.apellido_materno', 'u.correo', 'u.celular', 'u.activo',
                    DB::raw($instrumentosSql),
                    DB::raw('count(distinct e.id_estudiante) as estudiantes'),
                ])
                ->groupBy('p.id_profesor', 'u.id_usuario', 'u.nombres', 'u.apellido_paterno', 'u.apellido_materno', 'u.correo', 'u.celular', 'u.activo')
                ->orderBy('u.apellido_paterno')
                ->get()
                ->map(fn ($p) => [
                    'id' => (int) $p->id_profesor,
                    'id_usuario' => (int) $p->id_usuario,
                    'nombres' => $p->nombres,
                    'apellido_paterno' => $p->apellido_paterno,
                    'apellido_materno' => $p->apellido_materno,
                    'nombre' => trim($p->nombres.' '.$p->apellido_paterno.' '.$p->apellido_materno),
                    'correo' => $p->correo,
                    'celular' => $p->celular ?: 'Sin celular',
                    'especialidad' => $p->especialidad,
                    'estudiantes' => (int) $p->estudiantes,
                    'activo' => (bool) $p->activo,
                    'estudiantesLista' => ($estudiantesPorProfesor[(int) $p->id_profesor] ?? collect())->values()->all(),
                ])
                ->all();
        })();
    }

    public function estudiantesData(): array
    {
        return $this->memo['estudiantes'] ??= DB::table('estudiantes as e')
            ->join('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->leftJoin('elencos as el', 'el.id_elenco', '=', 'e.id_elenco')
            ->leftJoin('secciones as s', 's.id_seccion', '=', 'e.id_seccion')
            ->leftJoin('profesores as p', 'p.id_profesor', '=', 'e.id_profesor')
            ->leftJoin('usuarios as up', 'up.id_usuario', '=', 'p.id_usuario')
            ->leftJoin('gamificacion_perfiles as g', 'g.id_estudiante', '=', 'e.id_estudiante')
            ->select([
                'e.id_estudiante', 'e.id_elenco', 'e.id_profesor', 'e.id_seccion', 'u.id_usuario', 'u.nombres', 'u.apellido_paterno',
                'u.apellido_materno', 'u.correo', 'u.celular', 'u.ci', 'u.direccion', 'u.fecha_nacimiento', 'u.activo', 'el.nombre as elenco', 's.nombre as seccion',
                'up.nombres as prof_nombres', 'up.apellido_paterno as prof_apellido', DB::raw('coalesce(g.xp_total, 0) as xp'),
            ])
            ->orderBy('u.apellido_paterno')
            ->get()
            ->map(fn ($e) => [
                'id' => (int) $e->id_estudiante,
                'id_usuario' => (int) $e->id_usuario,
                'id_elenco' => $e->id_elenco ? (int) $e->id_elenco : null,
                'id_profesor' => $e->id_profesor ? (int) $e->id_profesor : null,
                'id_seccion' => $e->id_seccion ? (int) $e->id_seccion : null,
                'nombres' => $e->nombres,
                'apellido_paterno' => $e->apellido_paterno,
                'apellido_materno' => $e->apellido_materno,
                'nombre' => trim($e->nombres.' '.$e->apellido_paterno.' '.$e->apellido_materno),
                'correo' => $e->correo,
                'celular' => $e->celular,
                'ci' => $e->ci,
                'direccion' => $e->direccion,
                'fecha_nacimiento' => $e->fecha_nacimiento ? substr((string) $e->fecha_nacimiento, 0, 10) : null,
                'elenco' => $e->elenco ?: 'Sin elenco',
                'seccion' => $e->seccion ?: 'Sin nivel',
                'profesor' => $e->prof_nombres ? trim($e->prof_nombres.' '.$e->prof_apellido) : 'Sin profesor',
                'xp' => (int) $e->xp,
                'nivel' => app(GamificationService::class)->levelForXp((int) $e->xp),
                'activo' => (bool) $e->activo,
            ])
            ->all();
    }

    private function elencosData(): array
    {
        return $this->memo['elencos'] ??= DB::table('elencos as el')
            ->join('tipos_elencos as te', 'te.id_tipo', '=', 'el.id_tipo')
            ->leftJoin('estudiantes as e', 'e.id_elenco', '=', 'el.id_elenco')
            ->groupBy('el.id_elenco', 'el.nombre', 'te.nombre')
            ->orderBy('te.nombre')
            ->orderBy('el.nombre')
            ->get(['el.id_elenco', 'el.nombre', 'te.nombre as tipo', DB::raw('count(e.id_estudiante) as estudiantes')])
            ->map(fn ($el) => ['id' => (int) $el->id_elenco, 'nombre' => $el->nombre, 'tipo' => $el->tipo, 'estudiantes' => (int) $el->estudiantes])
            ->all();
    }

    private function directorProfileData(int $idUsuario): array
    {
        $usuario = Usuario::query()->whereKey($idUsuario)->first();

        return [
            'nombres' => $usuario->nombres ?? '',
            'apellido_paterno' => $usuario->apellido_paterno ?? '',
            'apellido_materno' => $usuario->apellido_materno ?? '',
            'correo' => $usuario->correo ?? '',
            'ci' => $usuario->ci ?? '',
            'celular' => $usuario->celular ?? '',
            'direccion' => $usuario->direccion ?? '',
            'fecha_nacimiento' => $usuario?->fecha_nacimiento?->toDateString() ?? '',
            'trayectoria' => $usuario->trayectoria ?? '',
            'puedeGuardarTrayectoria' => true,
        ];
    }

    public function actividadData(): array
    {
        return $this->memo['actividad'] ??= [
            'items' => collect()
                ->merge($this->actividadTareas())
                ->merge($this->actividadEntregas())
                ->merge($this->actividadPracticas())
                ->sortByDesc(fn ($i) => strtotime((string) $i['fechaOrden']) ?: 0)
                ->take(15)
                ->values()
                ->all(),
            'pendientesBackend' => [],
        ];
    }

    private function reportesData(): array
    {
        return [
            'resumen' => [
                ['label' => 'Usuarios totales', 'value' => DB::table('usuarios')->count()],
                ['label' => 'Estudiantes', 'value' => DB::table('estudiantes')->count()],
                ['label' => 'Profesores', 'value' => DB::table('profesores')->count()],
                ['label' => 'Actividad reciente', 'value' => count($this->actividadData()['items'])],
            ],
            'descargas' => [
                ['tipo' => 'estudiantes', 'titulo' => 'Reporte de estudiantes', 'detalle' => 'Elenco, profesor, nivel y XP.'],
                ['tipo' => 'profesores', 'titulo' => 'Reporte de profesores', 'detalle' => 'Especialidad, contacto y alumnos vinculados.'],
                ['tipo' => 'actividad', 'titulo' => 'Reporte de actividad', 'detalle' => 'Tareas, entregas y prácticas recientes.'],
                ['tipo' => 'ranking', 'titulo' => 'Ranking de XP', 'detalle' => 'Posiciones calculadas desde el libro de XP.'],
            ],
        ];
    }

    /** Cuantas cuentas hay de cada rol. */
    private function cuentasPorRol(): array
    {
        return DB::table('usuarios as u')
            ->join('roles as r', 'r.id_rol', '=', 'u.id_rol')
            ->groupBy('r.nombre')
            ->orderBy('r.nombre')
            ->get(['r.nombre', DB::raw('count(*) as total')])
            ->map(fn ($rol) => ['label' => ucfirst($rol->nombre), 'value' => (int) $rol->total])
            ->all();
    }

    public static function nombreAccion(string $accion): string
    {
        return [
            'login' => 'Ingreso correcto',
            'login_fallido' => 'Ingreso fallido',
            'login_movil' => 'Ingreso app móvil',
            'login_movil_fallido' => 'Ingreso móvil fallido',
            'logout' => 'Cierre de sesión',
            'cambio_contrasena' => 'Cambio de contraseña',
            'cambio_correo' => 'Cambio de correo',
            'recuperacion_solicitada' => 'Recuperacion solicitada',
            'recuperacion_completada' => 'Contraseña restablecida',
            'usuario_desactivado' => 'Usuario desactivado',
            'usuario_reactivado' => 'Usuario reactivado',
            'usuario_editado' => 'Usuario editado',
            'padre_creado' => 'Cuenta de padre creada',
            'padre_vinculado' => 'Padre vinculado',
            'padre_desvinculado' => 'Padre desvinculado',
            'profesor_creado' => 'Profesor creado',
            'comunicado_enviado' => 'Comunicado enviado',
            'reinicio_contrasena_admin' => 'Reinicio de contraseña (director)',
            'reporte_descargado' => 'Reporte descargado',
            'registro_estudiante' => 'Registro de estudiante',
        ][$accion] ?? ucfirst(str_replace('_', ' ', $accion));
    }

    private function ayudaData(): array
    {
        return [
            ['titulo' => 'Reportes', 'detalle' => 'Descarga CSV de estudiantes, profesores, actividad y ranking. Las celdas peligrosas se neutralizan.'],
            ['titulo' => 'Bitácora de seguridad', 'detalle' => 'Ingresos, fallos, cambios de contraseña y acciones del director.'],
            ['titulo' => 'Usuarios', 'detalle' => 'Edita, desactiva o reactiva cuentas sin borrar su historial académico.'],
            ['titulo' => 'Cursos', 'detalle' => 'Crea cursos, módulos, lecciones y preguntas; inscribe estudiantes o elencos completos.'],
        ];
    }

    private function alarmasData(): array
    {
        $alarmas = [];

        $vencidas = DB::table('tareas as t')
            ->whereNotNull('t.fecha_limite')
            ->whereDate('t.fecha_limite', '<', now()->toDateString())
            ->whereNotNull('t.id_estudiante')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('entregas_tareas as et')->whereColumn('et.id_tarea', 't.id_tarea'))
            ->count();
        if ($vencidas > 0) {
            $alarmas[] = ['nivel' => 'Alta', 'titulo' => 'Tareas vencidas sin entrega', 'detalle' => $vencidas.' tareas individuales vencieron sin entrega.'];
        }

        $porRevisar = DB::table('entregas_tareas')->where('estado', 'entregada')->where('entregado_at', '<', now()->subDays(7))->count();
        if ($porRevisar > 0) {
            $alarmas[] = ['nivel' => 'Media', 'titulo' => 'Entregas sin revisar', 'detalle' => $porRevisar.' entregas esperan calificación hace más de 7 días.'];
        }

        $sinProfesor = DB::table('estudiantes')->whereNull('id_profesor')->count();
        if ($sinProfesor > 0) {
            $alarmas[] = ['nivel' => 'Media', 'titulo' => 'Estudiantes sin profesor', 'detalle' => $sinProfesor.' estudiantes necesitan asignación.'];
        }

        $fallidos = DB::table('auditoria')->where('accion', 'like', 'login%fallido')->where('created_at', '>=', now()->subDay())->count();
        if ($fallidos >= 20) {
            $alarmas[] = ['nivel' => 'Alta', 'titulo' => 'Muchos ingresos fallidos', 'detalle' => $fallidos.' intentos fallidos en las últimas 24 horas.'];
        }

        $sinElenco = DB::table('estudiantes')->whereNull('id_elenco')->count();
        if ($sinElenco > 0) {
            $alarmas[] = ['nivel' => 'Baja', 'titulo' => 'Estudiantes sin elenco', 'detalle' => $sinElenco.' estudiantes no pertenecen a una agrupación.'];
        }

        return $alarmas ?: [['nivel' => 'Normal', 'titulo' => 'Sin alarmas activas', 'detalle' => 'No hay pendientes críticos con los datos actuales.']];
    }

    private function actividadTareas(): array
    {
        return DB::table('tareas as t')
            ->leftJoin('profesores as p', 'p.id_profesor', '=', 't.id_profesor')
            ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'p.id_usuario')
            ->orderByDesc('t.fecha_creacion')
            ->limit(6)
            ->get(['t.titulo', 't.fecha_creacion', 'u.nombres', 'u.apellido_paterno'])
            ->map(fn ($t) => [
                'tipo' => 'Tarea asignada',
                'titulo' => $t->titulo,
                'detalle' => 'Por '.trim(($t->nombres ?? 'Profesor').' '.($t->apellido_paterno ?? '')),
                'fecha' => $this->formatDate($t->fecha_creacion),
                'fechaOrden' => $t->fecha_creacion,
            ])->all();
    }

    private function actividadEntregas(): array
    {
        return DB::table('entregas_tareas as et')
            ->join('tareas as t', 't.id_tarea', '=', 'et.id_tarea')
            ->join('estudiantes as e', 'e.id_estudiante', '=', 'et.id_estudiante')
            ->join('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->orderByDesc('et.updated_at')
            ->limit(6)
            ->get(['et.estado', 'et.calificacion', 'et.updated_at', 't.titulo', 'u.nombres', 'u.apellido_paterno'])
            ->map(fn ($e) => [
                'tipo' => 'Entrega '.$e->estado,
                'titulo' => trim($e->nombres.' '.$e->apellido_paterno),
                'detalle' => $e->titulo.($e->calificacion !== null ? ' | Nota: '.(int) $e->calificacion : ''),
                'fecha' => $this->formatDate($e->updated_at),
                'fechaOrden' => $e->updated_at,
            ])->all();
    }

    private function actividadPracticas(): array
    {
        return DB::table('practicas_autonomas as p')
            ->join('estudiantes as e', 'e.id_estudiante', '=', 'p.id_estudiante')
            ->join('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->where('p.estado', 'finalizada')
            ->orderByDesc('p.created_at')
            ->limit(6)
            ->get(['p.tipo', 'p.duracion_segundos', 'p.xp_ganado', 'p.created_at', 'u.nombres', 'u.apellido_paterno'])
            ->map(fn ($p) => [
                'tipo' => 'Practica de '.$p->tipo,
                'titulo' => trim($p->nombres.' '.$p->apellido_paterno),
                'detalle' => round($p->duracion_segundos / 60, 1).' min | +'.$p->xp_ganado.' XP',
                'fecha' => $this->formatDate($p->created_at),
                'fechaOrden' => $p->created_at,
            ])->all();
    }

    private function formatDate(?string $date): string
    {
        return $date ? date('d/m/Y H:i', strtotime($date)) : 'Sin fecha';
    }
}
