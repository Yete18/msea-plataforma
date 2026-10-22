<?php

namespace App\Http\Controllers;

use App\Services\CursoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCursoController extends Controller
{
    public function index(CursoService $cursos)
    {
        return view('admin.cursos', [
            'cursos' => $cursos->listaAdmin(),
            'profesores' => $this->profesores(),
            'niveles' => DB::table('secciones')->orderBy('id_seccion')->pluck('nombre'),
            'instrumentos' => DB::table('instrumentos')->orderBy('nombre')->pluck('nombre'),
        ]);
    }

    public function store(Request $request, CursoService $cursos)
    {
        $curso = $cursos->guardarCurso($this->validarCurso($request));

        return redirect('/dashboard-admin/cursos/'.$curso->id_curso)->with('success', 'Curso creado. Ahora agrega sus modulos.');
    }

    public function show(int $idCurso, CursoService $cursos)
    {
        return view('admin.curso', $cursos->detalleAdmin($idCurso) + [
            'profesores' => $this->profesores(),
            'niveles' => DB::table('secciones')->orderBy('id_seccion')->pluck('nombre'),
            'instrumentos' => DB::table('instrumentos')->orderBy('nombre')->pluck('nombre'),
            'elencos' => DB::table('elencos')->orderBy('nombre')->get(['id_elenco', 'nombre']),
            'estudiantes' => DB::table('estudiantes as e')->join('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
                ->where('u.activo', true)->orderBy('u.apellido_paterno')->get(['e.id_estudiante', 'u.nombres', 'u.apellido_paterno']),
        ]);
    }

    public function update(Request $request, int $idCurso, CursoService $cursos)
    {
        $cursos->guardarCurso($this->validarCurso($request), $idCurso);

        return back()->with('success', 'Curso actualizado.');
    }

    public function storeModulo(Request $request, int $idCurso, CursoService $cursos)
    {
        $cursos->guardarModulo($idCurso, $this->validarModulo($request));

        return back()->with('success', 'Modulo agregado.');
    }

    public function updateModulo(Request $request, int $idCurso, int $idModulo, CursoService $cursos)
    {
        $cursos->guardarModulo($idCurso, $this->validarModulo($request), $idModulo);

        return back()->with('success', 'Modulo actualizado.');
    }

    public function destroyModulo(int $idCurso, int $idModulo, CursoService $cursos)
    {
        $cursos->eliminarModulo($idCurso, $idModulo);

        return back()->with('success', 'Módulo eliminado (o despublicado si ya tenía avance de estudiantes).');
    }

    public function storeLeccion(Request $request, int $idCurso, int $idModulo, CursoService $cursos)
    {
        $this->moduloDelCurso($idCurso, $idModulo);
        $cursos->guardarLeccion($idModulo, $this->validarLeccion($request));

        return back()->with('success', 'Lección agregada.');
    }

    public function updateLeccion(Request $request, int $idCurso, int $idModulo, int $idLeccion, CursoService $cursos)
    {
        $this->moduloDelCurso($idCurso, $idModulo);
        $cursos->guardarLeccion($idModulo, $this->validarLeccion($request), $idLeccion);

        return back()->with('success', 'Lección actualizada.');
    }

    public function destroyLeccion(int $idCurso, int $idModulo, int $idLeccion, CursoService $cursos)
    {
        $this->moduloDelCurso($idCurso, $idModulo);
        $cursos->eliminarLeccion($idModulo, $idLeccion);

        return back()->with('success', 'Lección eliminada (o despublicada si ya tenía avance).');
    }

    public function storePregunta(Request $request, CursoService $cursos, ?int $idTeoria = null)
    {
        $datos = $request->validate([
            'id_leccion' => ['nullable', 'integer', 'exists:lecciones,id_leccion'],
            'titulo' => ['required', 'string', 'max:150'],
            'pregunta' => ['required', 'string', 'max:1000'],
            'opciones' => ['required', 'array', 'min:2', 'max:6'],
            'opciones.*' => ['nullable', 'string', 'max:150'],
            'respuesta_correcta' => ['required', 'string', 'max:150'],
            'xp' => ['nullable', 'integer', 'min:0', 'max:100'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $datos['activo'] = $request->boolean('activo', true);

        $cursos->guardarPregunta($datos, $idTeoria);

        return back()->with('success', 'Pregunta guardada. La respuesta correcta nunca se envia al navegador del estudiante.');
    }

    public function inscribir(Request $request, int $idCurso, CursoService $cursos)
    {
        $datos = $request->validate([
            'estudiantes' => ['nullable', 'array', 'max:500'],
            'estudiantes.*' => ['integer', 'exists:estudiantes,id_estudiante'],
            'id_elenco' => ['nullable', 'integer', 'exists:elencos,id_elenco'],
        ]);

        $nuevos = $cursos->inscribir($idCurso, $datos['estudiantes'] ?? [], $datos['id_elenco'] ?? null);

        return back()->with('success', "Inscritos {$nuevos} estudiante(s) nuevo(s).");
    }

    public function desinscribir(int $idCurso, int $idEstudiante, CursoService $cursos)
    {
        $cursos->desinscribir($idCurso, $idEstudiante);

        return back()->with('success', 'Estudiante retirado del curso. Su avance se conserva.');
    }

    private function validarCurso(Request $request): array
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'instrumento' => ['nullable', 'string', 'max:50'],
            'nivel' => ['nullable', 'string', 'max:50'],
            'id_profesor' => ['nullable', 'integer', 'exists:profesores,id_profesor'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $datos['activo'] = $request->boolean('activo', true);

        return $datos;
    }

    private function validarModulo(Request $request): array
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'orden' => ['nullable', 'integer', 'min:1', 'max:999'],
            'publicado' => ['nullable', 'boolean'],
        ]);
        $datos['publicado'] = $request->boolean('publicado', true);

        return $datos;
    }

    private function validarLeccion(Request $request): array
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'contenido' => ['nullable', 'string', 'max:10000'],
            'tipo' => ['required', 'in:teoria,tecnica,ritmo,afinacion,repertorio'],
            'orden' => ['nullable', 'integer', 'min:1', 'max:999'],
            'xp' => ['nullable', 'integer', 'min:0', 'max:200'],
            'publicado' => ['nullable', 'boolean'],
        ]);
        $datos['publicado'] = $request->boolean('publicado', true);

        return $datos;
    }

    private function moduloDelCurso(int $idCurso, int $idModulo): void
    {
        abort_unless(DB::table('modulos')->where('id_modulo', $idModulo)->where('id_curso', $idCurso)->exists(), 404);
    }

    private function profesores()
    {
        return DB::table('profesores as p')->join('usuarios as u', 'u.id_usuario', '=', 'p.id_usuario')
            ->where('u.activo', true)->orderBy('u.apellido_paterno')
            ->get(['p.id_profesor', 'u.nombres', 'u.apellido_paterno']);
    }
}
