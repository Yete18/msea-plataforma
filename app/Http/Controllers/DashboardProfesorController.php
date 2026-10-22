<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarContrasenaRequest;
use App\Http\Requests\GuardarTareaRequest;
use App\Models\Usuario;
use App\Services\CuentaSeguridadService;
use App\Services\ProfesorDashboardService;
use App\Services\ProfilePhotoService;
use App\Services\TareaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardProfesorController extends Controller
{
    public function show(ProfesorDashboardService $dashboard, TareaService $tareas)
    {
        $profesor = $tareas->profesorDeUsuario((int) session('usuario_id'));

        if (! $profesor) {
            session()->invalidate();

            return redirect('/login')->with('error', 'No encontramos el perfil de profesor para este usuario');
        }

        return view('dashboard-profesor', $dashboard->viewData($profesor));
    }

    public function storeTask(GuardarTareaRequest $request, TareaService $tareas)
    {
        $tarea = $tareas->crearTarea($this->profesor($tareas), $request->validated(), $request->file('material'));

        return response()->json(['message' => 'Tarea creada correctamente', 'tarea' => $tarea], 201);
    }

    public function updateTask(GuardarTareaRequest $request, int $idTarea, TareaService $tareas)
    {
        $tarea = $tareas->actualizarTarea($this->profesor($tareas), $idTarea, $request->validated(), $request->file('material'));

        return response()->json(['message' => 'Tarea actualizada', 'tarea' => $tarea]);
    }

    public function destroyTask(int $idTarea, TareaService $tareas)
    {
        $tareas->eliminarTarea($this->profesor($tareas), $idTarea);

        return response()->json(['message' => 'Tarea eliminada']);
    }

    public function taskSubmissions(int $idTarea, TareaService $tareas)
    {
        return response()->json(['entregas' => $tareas->entregasDeTarea($this->profesor($tareas), $idTarea)]);
    }

    public function gradeTask(Request $request, int $idEntrega, TareaService $tareas)
    {
        $validated = $request->validate([
            'calificacion' => ['required', 'numeric', 'min:0', 'max:100'],
            'comentario_profesor' => ['nullable', 'string', 'max:1000'],
        ]);

        $resultado = $tareas->calificar(
            $this->profesor($tareas),
            $idEntrega,
            (float) $validated['calificacion'],
            $validated['comentario_profesor'] ?? null
        );

        return response()->json([
            'message' => $resultado['recalificacion'] ? 'Calificacion actualizada (el XP no se vuelve a otorgar)' : 'Tarea calificada correctamente',
            'xp_otorgado' => $resultado['xp_otorgado'],
        ]);
    }

    public function returnTask(Request $request, int $idEntrega, TareaService $tareas)
    {
        $validated = $request->validate(['comentario_profesor' => ['required', 'string', 'min:3', 'max:1000']]);

        $tareas->devolver($this->profesor($tareas), $idEntrega, $validated['comentario_profesor']);

        return response()->json(['message' => 'Entrega devuelta para correccion']);
    }

    public function studentProgress(int $idEstudiante, ProfesorDashboardService $dashboard, TareaService $tareas)
    {
        $datos = $dashboard->progresoEstudiante($this->profesor($tareas), $idEstudiante);

        if (! $datos) {
            return response()->json(['message' => 'Estudiante no encontrado o no asignado a ti'], 404);
        }

        return response()->json($datos);
    }

    public function updateProfile(Request $request, CuentaSeguridadService $cuenta, ProfilePhotoService $fotos)
    {
        $idUsuario = (int) session('usuario_id');
        $request->merge(['correo' => mb_strtolower(trim((string) $request->input('correo')))]);

        $validated = $request->validate([
            'nombres' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['nullable', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:100', Rule::unique('usuarios', 'correo')->ignore($idUsuario, 'id_usuario')],
            'celular' => ['nullable', 'string', 'max:20'],
            'contrasena_actual' => ['nullable', 'string', 'max:255'],
            'foto' => ['nullable', 'string', 'max:3000000', 'regex:/^data:image\/(png|jpe?g|webp);base64,/'],
        ]);

        $anterior = (string) Usuario::query()->whereKey($idUsuario)->value('correo');
        $cambia = $cuenta->exigirReautenticacionSiCambiaCorreo($idUsuario, $validated['correo'], $validated['contrasena_actual'] ?? null);

        $datos = collect($validated)->only(['nombres', 'apellido_paterno', 'apellido_materno', 'correo', 'celular'])->all();
        if (! empty($validated['foto'])) {
            $datos['foto'] = $fotos->storeIfBase64($validated['foto'], (string) Usuario::query()->whereKey($idUsuario)->value('foto'));
        }
        Usuario::query()->whereKey($idUsuario)->update($datos);

        if ($cambia) {
            $cuenta->despuesDeCambiarCorreo($idUsuario, $anterior, $validated['correo']);
        }

        session(['nombre' => $datos['nombres'], 'apellido_paterno' => $datos['apellido_paterno'] ?? null, 'correo' => $datos['correo']]);

        return response()->json([
            'message' => 'Perfil actualizado',
            'foto' => isset($datos['foto']) ? $fotos->publicValue($datos['foto'], $idUsuario) : null,
        ]);
    }

    public function changePassword(CambiarContrasenaRequest $request, CuentaSeguridadService $cuenta)
    {
        $cuenta->cambiarContrasena((int) session('usuario_id'), $request->validated('contrasena_actual'), $request->validated('contrasena'));

        return response()->json(['message' => 'Contraseña actualizada']);
    }

    private function profesor(TareaService $tareas): object
    {
        $profesor = $tareas->profesorDeUsuario((int) session('usuario_id'));
        abort_unless($profesor, 404, 'No encontramos el perfil de profesor');

        return $profesor;
    }
}
