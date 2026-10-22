<?php

namespace App\Http\Controllers;

use App\Services\ArchivoPrivadoService;
use App\Services\ProfilePhotoService;
use App\Services\TareaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Unica puerta de acceso a archivos privados: comprueba en cada descarga que
 * el usuario de la sesion tenga relacion con la entrega o la tarea.
 */
class ArchivoController extends Controller
{
    public function entrega(Request $request, int $idEntrega, TareaService $tareas, ArchivoPrivadoService $archivos)
    {
        $entrega = DB::table('entregas_tareas')->where('id_entrega', $idEntrega)->first();

        abort_unless($entrega && $entrega->archivo_path, 404);
        abort_unless($tareas->puedeVerEntrega((int) session('usuario_id'), (string) session('rol'), $entrega), 404);

        return $archivos->respuesta($entrega->archivo_path, (string) $entrega->archivo_nombre, $entrega->archivo_mime, $request->boolean('descargar'));
    }

    public function materialTarea(Request $request, int $idTarea, TareaService $tareas, ArchivoPrivadoService $archivos)
    {
        $tarea = DB::table('tareas')->where('id_tarea', $idTarea)->first();

        abort_unless($tarea && $tarea->material_path, 404);
        abort_unless($tareas->puedeVerMaterial((int) session('usuario_id'), (string) session('rol'), $tarea), 404);

        return $archivos->respuesta($tarea->material_path, (string) $tarea->material_nombre, $tarea->material_mime, $request->boolean('descargar'));
    }

    /** Foto de perfil (privada): solo para quien tiene relacion con la persona. */
    public function foto(int $idUsuario, ProfilePhotoService $fotos, ArchivoPrivadoService $archivos)
    {
        abort_unless($fotos->puedeVer((int) session('usuario_id'), (string) session('rol'), $idUsuario), 404);

        $path = $fotos->rutaPrivada(DB::table('usuarios')->where('id_usuario', $idUsuario)->value('foto'));
        abort_unless($path !== null, 404);

        $mime = match (pathinfo($path, PATHINFO_EXTENSION)) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return $archivos->respuesta($path, 'foto.'.pathinfo($path, PATHINFO_EXTENSION), $mime);
    }
}
