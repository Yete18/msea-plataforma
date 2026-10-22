<?php

namespace App\Http\Controllers;

use App\Services\CursoService;
use App\Services\TareaService;
use App\Services\TeoriaService;

class CursoEstudianteController extends Controller
{
    public function show(int $idCurso, CursoService $cursos, TeoriaService $teoria, TareaService $tareas)
    {
        $estudiante = $tareas->estudianteDeUsuario((int) session('usuario_id'));
        abort_unless($estudiante, 404);

        $detalle = $cursos->detalleParaEstudiante($estudiante, $idCurso, $teoria);
        abort_unless($detalle, 404);

        return view('curso-estudiante', $detalle);
    }

    public function completeLesson(int $idLeccion, CursoService $cursos, TareaService $tareas)
    {
        $estudiante = $tareas->estudianteDeUsuario((int) session('usuario_id'));
        abort_unless($estudiante, 404);

        $r = $cursos->completarLeccion($estudiante, $idLeccion);

        return response()->json([
            'message' => $r['ya_completada'] ? 'Esta lección ya estaba completada.' : 'Lección completada',
            'xp' => $r['xp'],
        ]);
    }
}
