<?php

namespace App\Http\Controllers;

use App\Services\CsvExporter;
use App\Services\MensajeService;
use App\Services\PadreDashboardService;
use Illuminate\Support\Facades\DB;

class DashboardPadreController extends Controller
{
    public function show(PadreDashboardService $padres, MensajeService $mensajes)
    {
        $idUsuario = (int) session('usuario_id');

        return view('dashboard-padre', [
            'estudiantes' => $padres->hijos($idUsuario),
            'padre' => DB::table('usuarios')->where('id_usuario', $idUsuario)->first(['nombres', 'apellido_paterno', 'correo']),
            'notificaciones' => DB::table('notificaciones')->where('id_usuario', $idUsuario)->orderByDesc('created_at')->limit(10)->get(),
            'mensajes' => $mensajes->bandeja($idUsuario, 20),
            'contactos' => $mensajes->contactos($idUsuario, 'padre'),
        ]);
    }

    /** Reporte CSV de un hijo vinculado. Un estudiante no vinculado responde 404. */
    public function report(int $idEstudiante, PadreDashboardService $padres, CsvExporter $csv)
    {
        abort_unless(in_array($idEstudiante, $padres->idsHijos((int) session('usuario_id')), true), 404);

        $hijo = $padres->resumenHijo($idEstudiante);
        $filas = [
            ['Estudiante', $hijo['nombre']],
            ['Nivel MSEA', $hijo['nivel']],
            ['XP total', $hijo['xp_total']],
            ['Racha actual (días)', $hijo['racha']],
            ['Minutos de práctica (total)', $hijo['total_minutos']],
            ['Lecciones completadas', $hijo['lecciones_completadas']],
            [],
            ['Tarea', 'Vence', 'Estado', 'Calificacion', 'Observacion del profesor'],
        ];
        foreach ($hijo['tareas'] as $t) {
            $filas[] = [$t['titulo'], $t['limite'], $t['estado'], $t['calificacion'] ?? '', $t['comentario_profesor'] ?? ''];
        }

        return $csv->descargar('reporte_'.$idEstudiante.'.csv', [], $filas);
    }
}
