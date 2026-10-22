<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\Leccion;
use App\Models\LeccionProgreso;
use App\Models\Modulo;
use App\Models\TeoriaContenido;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Flujo academico: Curso -> Modulos -> Lecciones -> Preguntas -> Progreso.
 * Reutiliza las tablas existentes (cursos, modulos, lecciones,
 * teoria_contenidos, curso_estudiante, leccion_progreso).
 */
class CursoService
{
    public function __construct(private readonly GamificationService $gamificacion) {}

    // ---------------------------------------------------------------------
    // Estudiante
    // ---------------------------------------------------------------------

    public function cursosDeEstudiante(int $idEstudiante): array
    {
        $cursos = DB::table('curso_estudiante as ce')
            ->join('cursos as c', 'c.id_curso', '=', 'ce.id_curso')
            ->where('ce.id_estudiante', $idEstudiante)
            ->where('c.activo', true)
            ->orderBy('c.titulo')
            ->get(['c.id_curso', 'c.titulo', 'c.descripcion', 'c.nivel', 'c.instrumento']);

        // ARQ-01: lecciones de todos los cursos y avance del alumno en 2 consultas.
        $porCurso = $this->leccionesPublicadasDe($cursos->pluck('id_curso')->all());
        $completadas = DB::table('leccion_progreso')->where('id_estudiante', $idEstudiante)
            ->where('estado', 'completada')->pluck('id_leccion')->map(fn ($id) => (int) $id);

        return $cursos
            ->map(function ($curso) use ($porCurso, $completadas) {
                $lecciones = collect($porCurso[(int) $curso->id_curso] ?? []);
                $hechas = $lecciones->intersect($completadas)->values();
                $siguiente = $lecciones->first(fn ($id) => ! $hechas->contains($id));

                return [
                    'id' => (int) $curso->id_curso,
                    'titulo' => $curso->titulo,
                    'descripcion' => $curso->descripcion,
                    'nivel' => $curso->nivel,
                    'instrumento' => $curso->instrumento,
                    'lecciones' => $lecciones->count(),
                    'completadas' => $hechas->count(),
                    'progreso' => $lecciones->count() ? (int) round($hechas->count() * 100 / $lecciones->count()) : 0,
                    'siguienteLeccion' => $siguiente ? (int) $siguiente : null,
                    'url' => url('/dashboard-estudiante/cursos/'.$curso->id_curso),
                ];
            })
            ->all();
    }

    public function estaInscrito(int $idEstudiante, int $idCurso): bool
    {
        return DB::table('curso_estudiante as ce')
            ->join('cursos as c', 'c.id_curso', '=', 'ce.id_curso')
            ->where('ce.id_estudiante', $idEstudiante)
            ->where('ce.id_curso', $idCurso)
            ->where('c.activo', true)
            ->exists();
    }

    public function detalleParaEstudiante(object $estudiante, int $idCurso, TeoriaService $teoria): ?array
    {
        if (! $this->estaInscrito((int) $estudiante->id_estudiante, $idCurso)) {
            return null;
        }

        $curso = Curso::query()->findOrFail($idCurso);
        $progreso = DB::table('leccion_progreso')->where('id_estudiante', $estudiante->id_estudiante)->pluck('estado', 'id_leccion');

        $modulos = Modulo::query()
            ->where('id_curso', $idCurso)
            ->where('publicado', true)
            ->orderBy('orden')->orderBy('id_modulo')
            ->with(['lecciones' => fn ($q) => $q->where('publicado', true)->orderBy('orden')->orderBy('id_leccion')])
            ->get()
            ->map(fn (Modulo $m) => [
                'id' => $m->id_modulo,
                'titulo' => $m->titulo,
                'descripcion' => $m->descripcion,
                'lecciones' => $m->lecciones->map(fn (Leccion $l) => [
                    'id' => $l->id_leccion,
                    'titulo' => $l->titulo,
                    'tipo' => $l->tipo,
                    'contenido' => $l->contenido,
                    'xp' => (int) $l->xp,
                    'estado' => $progreso[$l->id_leccion] ?? 'pendiente',
                    'preguntas' => $teoria->preguntasPara($estudiante, $l->id_leccion, 50),
                ])->all(),
            ]);

        $total = $modulos->sum(fn ($m) => count($m['lecciones']));
        $hechas = $modulos->sum(fn ($m) => collect($m['lecciones'])->where('estado', 'completada')->count());

        return [
            'curso' => ['id' => $curso->id_curso, 'titulo' => $curso->titulo, 'descripcion' => $curso->descripcion, 'nivel' => $curso->nivel, 'instrumento' => $curso->instrumento],
            'modulos' => $modulos->all(),
            'progreso' => $total ? (int) round($hechas * 100 / $total) : 0,
            'completadas' => $hechas,
            'total' => $total,
        ];
    }

    /**
     * Marca una leccion como completada. Si tiene preguntas, todas deben estar
     * respondidas correctamente. El XP se otorga una sola vez.
     */
    public function completarLeccion(object $estudiante, int $idLeccion): array
    {
        $leccion = DB::table('lecciones as l')
            ->join('modulos as m', 'm.id_modulo', '=', 'l.id_modulo')
            ->where('l.id_leccion', $idLeccion)
            ->where('l.publicado', true)
            ->where('m.publicado', true)
            ->first(['l.*', 'm.id_curso']);

        if (! $leccion || ! $this->estaInscrito((int) $estudiante->id_estudiante, (int) $leccion->id_curso)) {
            throw new NotFoundHttpException('Lección no disponible');
        }

        $preguntas = DB::table('teoria_contenidos')->where('id_leccion', $idLeccion)->where('activo', true)->pluck('id_teoria');
        if ($preguntas->isNotEmpty()) {
            $acertadas = DB::table('teoria_intentos')->where('id_estudiante', $estudiante->id_estudiante)
                ->whereIn('id_teoria', $preguntas)->where('correcta', true)->distinct()->count('id_teoria');
            if ($acertadas < $preguntas->count()) {
                throw ValidationException::withMessages(['leccion' => 'Responde correctamente todas las preguntas de la lección para completarla.']);
            }
        }

        $registro = LeccionProgreso::query()->firstOrCreate(
            ['id_leccion' => $idLeccion, 'id_estudiante' => $estudiante->id_estudiante],
            ['estado' => 'pendiente']
        );

        if ($registro->estado !== 'completada') {
            $registro->update(['estado' => 'completada', 'completado_at' => now()]);
        }

        $xp = $this->gamificacion->otorgarXp(
            (int) $estudiante->id_estudiante,
            (int) $leccion->xp,
            'leccion',
            'leccion:'.$idLeccion.':'.$estudiante->id_estudiante,
            'Lección completada: '.$leccion->titulo
        );
        $this->gamificacion->registrarActividadDiaria((int) $estudiante->id_estudiante);

        return ['xp' => $xp, 'ya_completada' => $xp === 0];
    }

    // ---------------------------------------------------------------------
    // Director: gestion
    // ---------------------------------------------------------------------

    public function listaAdmin(): array
    {
        return Curso::query()
            ->withCount(['modulos', 'estudiantes'])
            ->orderByDesc('activo')
            ->orderBy('titulo')
            ->get()
            ->map(fn (Curso $c) => [
                'id' => $c->id_curso, 'titulo' => $c->titulo, 'nivel' => $c->nivel, 'instrumento' => $c->instrumento,
                'activo' => (bool) $c->activo, 'modulos' => $c->modulos_count, 'estudiantes' => $c->estudiantes_count,
                'profesor' => $c->id_profesor,
            ])
            ->all();
    }

    public function guardarCurso(array $datos, ?int $idCurso = null): Curso
    {
        $campos = [
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'] ?? null,
            'instrumento' => $datos['instrumento'] ?? null,
            'nivel' => $datos['nivel'] ?? 'Inicial',
            'id_profesor' => $datos['id_profesor'] ?? null,
            'activo' => (bool) ($datos['activo'] ?? true),
        ];

        if ($idCurso) {
            $curso = Curso::query()->findOrFail($idCurso);
            $curso->update($campos);

            return $curso;
        }

        return Curso::query()->create($campos);
    }

    public function guardarModulo(int $idCurso, array $datos, ?int $idModulo = null): Modulo
    {
        Curso::query()->findOrFail($idCurso);
        $campos = [
            'id_curso' => $idCurso,
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'] ?? null,
            'orden' => (int) ($datos['orden'] ?? ((int) Modulo::query()->where('id_curso', $idCurso)->max('orden') + 1)),
            'publicado' => (bool) ($datos['publicado'] ?? true),
        ];

        if ($idModulo) {
            $modulo = Modulo::query()->where('id_curso', $idCurso)->findOrFail($idModulo);
            $modulo->update($campos);

            return $modulo;
        }

        return Modulo::query()->create($campos);
    }

    public function eliminarModulo(int $idCurso, int $idModulo): void
    {
        $modulo = Modulo::query()->where('id_curso', $idCurso)->findOrFail($idModulo);
        $conAvance = DB::table('leccion_progreso as lp')->join('lecciones as l', 'l.id_leccion', '=', 'lp.id_leccion')
            ->where('l.id_modulo', $idModulo)->exists();

        if ($conAvance) {
            // Conserva el historial: se despublica en vez de borrar.
            $modulo->update(['publicado' => false]);

            return;
        }

        $modulo->delete();
    }

    public function guardarLeccion(int $idModulo, array $datos, ?int $idLeccion = null): Leccion
    {
        Modulo::query()->findOrFail($idModulo);
        $campos = [
            'id_modulo' => $idModulo,
            'titulo' => $datos['titulo'],
            'contenido' => $datos['contenido'] ?? null,
            'tipo' => $datos['tipo'] ?? 'teoria',
            'orden' => (int) ($datos['orden'] ?? ((int) Leccion::query()->where('id_modulo', $idModulo)->max('orden') + 1)),
            'xp' => (int) ($datos['xp'] ?? 20),
            'publicado' => (bool) ($datos['publicado'] ?? true),
        ];

        if ($idLeccion) {
            $leccion = Leccion::query()->where('id_modulo', $idModulo)->findOrFail($idLeccion);
            $leccion->update($campos);

            return $leccion;
        }

        return Leccion::query()->create($campos);
    }

    public function eliminarLeccion(int $idModulo, int $idLeccion): void
    {
        $leccion = Leccion::query()->where('id_modulo', $idModulo)->findOrFail($idLeccion);

        if (DB::table('leccion_progreso')->where('id_leccion', $idLeccion)->exists()) {
            $leccion->update(['publicado' => false]);

            return;
        }

        $leccion->delete();
    }

    public function guardarPregunta(array $datos, ?int $idTeoria = null): TeoriaContenido
    {
        $opciones = array_values(array_filter(array_map('trim', (array) $datos['opciones']), fn ($o) => $o !== ''));

        if (count($opciones) < 2) {
            throw ValidationException::withMessages(['opciones' => 'Escribe al menos dos opciones.']);
        }
        if (! in_array(trim($datos['respuesta_correcta']), $opciones, true)) {
            throw ValidationException::withMessages(['respuesta_correcta' => 'La respuesta correcta debe ser una de las opciones.']);
        }

        $campos = [
            'id_leccion' => $datos['id_leccion'] ?? null,
            'titulo' => $datos['titulo'],
            'pregunta' => $datos['pregunta'],
            'opciones' => $opciones,
            'respuesta_correcta' => trim($datos['respuesta_correcta']),
            'nivel' => $datos['nivel'] ?? 'Inicial',
            'xp' => (int) ($datos['xp'] ?? 10),
            'activo' => (bool) ($datos['activo'] ?? true),
        ];

        if ($idTeoria) {
            $pregunta = TeoriaContenido::query()->findOrFail($idTeoria);
            $pregunta->update($campos);

            return $pregunta;
        }

        return TeoriaContenido::query()->create($campos);
    }

    /** Inscribe estudiantes (lista o un elenco completo). Devuelve cuantos nuevos. */
    public function inscribir(int $idCurso, array $idsEstudiantes = [], ?int $idElenco = null): int
    {
        Curso::query()->findOrFail($idCurso);

        $ids = collect($idsEstudiantes)->map(fn ($id) => (int) $id);
        if ($idElenco) {
            $ids = $ids->merge(DB::table('estudiantes')->where('id_elenco', $idElenco)->pluck('id_estudiante'));
        }

        $nuevos = 0;
        foreach ($ids->unique() as $idEstudiante) {
            $nuevos += DB::table('curso_estudiante')->insertOrIgnore([
                'id_curso' => $idCurso,
                'id_estudiante' => $idEstudiante,
                'inscrito_at' => now(),
            ]);
        }

        return $nuevos;
    }

    public function desinscribir(int $idCurso, int $idEstudiante): void
    {
        DB::table('curso_estudiante')->where('id_curso', $idCurso)->where('id_estudiante', $idEstudiante)->delete();
    }

    public function detalleAdmin(int $idCurso): array
    {
        $curso = Curso::query()->with(['modulos.lecciones.teoria'])->findOrFail($idCurso);

        $inscritos = DB::table('curso_estudiante as ce')
            ->join('estudiantes as e', 'e.id_estudiante', '=', 'ce.id_estudiante')
            ->join('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->where('ce.id_curso', $idCurso)
            ->orderBy('u.apellido_paterno')
            ->get(['e.id_estudiante', 'u.nombres', 'u.apellido_paterno', 'ce.inscrito_at']);

        return ['curso' => $curso, 'inscritos' => $inscritos];
    }

    /** @return array<int, list<int>> id_curso => ids de lecciones publicadas, en orden. */
    private function leccionesPublicadasDe(array $idsCursos): array
    {
        if ($idsCursos === []) {
            return [];
        }

        $porCurso = [];
        DB::table('lecciones as l')
            ->join('modulos as m', 'm.id_modulo', '=', 'l.id_modulo')
            ->whereIn('m.id_curso', $idsCursos)
            ->where('m.publicado', true)
            ->where('l.publicado', true)
            ->orderBy('m.orden')->orderBy('m.id_modulo')->orderBy('l.orden')->orderBy('l.id_leccion')
            ->get(['m.id_curso', 'l.id_leccion'])
            ->each(function ($fila) use (&$porCurso) {
                $porCurso[(int) $fila->id_curso][] = (int) $fila->id_leccion;
            });

        return $porCurso;
    }
}
