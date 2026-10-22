<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarContrasenaRequest;
use App\Http\Requests\UpdateStudentProfileRequest;
use App\Models\Usuario;
use App\Services\CuentaSeguridadService;
use App\Services\GamificationService;
use App\Services\HabitosService;
use App\Services\PracticaService;
use App\Services\StudentDashboardService;
use App\Services\TareaService;
use App\Services\TeoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardEstudianteController extends Controller
{
    public function show(StudentDashboardService $studentDashboardService)
    {
        $payload = $studentDashboardService->dashboardPayload((int) session('usuario_id'));

        if (! $payload) {
            session()->invalidate();

            return redirect('/login')->with('error', 'No encontramos el perfil de estudiante para este usuario');
        }

        return view('dashboard-estudiante', $payload);
    }

    public function updateProfile(
        UpdateStudentProfileRequest $request,
        StudentDashboardService $studentDashboardService,
        CuentaSeguridadService $cuenta,
    ) {
        $idUsuario = (int) session('usuario_id');

        if (! $studentDashboardService->estudianteExiste($idUsuario)) {
            return response()->json(['message' => 'No encontramos el perfil de estudiante para este usuario'], 404);
        }

        $validated = $request->validated();
        $correoAnterior = (string) Usuario::query()->whereKey($idUsuario)->value('correo');
        $cambiaCorreo = $cuenta->exigirReautenticacionSiCambiaCorreo($idUsuario, $validated['correo'], $validated['contrasena_actual'] ?? null);
        unset($validated['contrasena_actual']);

        $payload = $studentDashboardService->updateProfile($idUsuario, $validated);

        if ($cambiaCorreo) {
            $cuenta->despuesDeCambiarCorreo($idUsuario, $correoAnterior, $payload['correo']);
        }

        session([
            'nombre' => $payload['nombres'],
            'apellido_paterno' => $payload['apellido_paterno'],
            'correo' => $payload['correo'],
        ]);

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'estudiante' => [
                'nombre' => $payload['nombres'],
                'apellidoPaterno' => $payload['apellido_paterno'],
                'apellidoMaterno' => $payload['apellido_materno'],
                'apellido' => trim(($payload['apellido_paterno'] ?? '').' '.($payload['apellido_materno'] ?? '')),
                'nombreCompleto' => trim($payload['nombres'].' '.($payload['apellido_paterno'] ?? '').' '.($payload['apellido_materno'] ?? '')),
                'email' => $payload['correo'],
                'celular' => $payload['celular'],
                'foto' => $payload['foto'] ?? null,
            ],
        ]);
    }

    public function changePassword(CambiarContrasenaRequest $request, CuentaSeguridadService $cuenta)
    {
        $cuenta->cambiarContrasena((int) session('usuario_id'), $request->validated('contrasena_actual'), $request->validated('contrasena'));

        return response()->json(['message' => 'Contraseña actualizada. Se cerraron tus sesiones en otros dispositivos.']);
    }

    public function startPractice(Request $request, PracticaService $practicas, TareaService $tareas)
    {
        $validated = $request->validate([
            'tipo' => ['required', 'string', 'in:'.implode(',', (array) config('msea.practica.tipos'))],
            'id_ejercicio' => ['nullable', 'integer', 'exists:ejercicios,id_ejercicio'],
        ]);

        $estudiante = $this->estudiante($tareas);
        $practica = $practicas->iniciar($estudiante, $validated['tipo'], $validated['id_ejercicio'] ?? null);

        return response()->json([
            'id_practica' => $practica->id_practica_autonoma,
            'iniciada_at' => $practica->iniciada_at->toIso8601String(),
            'duracion_minima_segundos' => (int) config('msea.xp.duracion_minima_segundos'),
        ], 201);
    }

    public function finishPractice(Request $request, int $idPractica, PracticaService $practicas, TareaService $tareas)
    {
        $metricas = $request->validate([
            'nota_objetivo' => ['nullable', 'string', 'max:10'],
            'nota_detectada' => ['nullable', 'string', 'max:10'],
            'frecuencia' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'desviacion_cents' => ['nullable', 'numeric', 'min:-200', 'max:200'],
            'bpm' => ['nullable', 'integer', 'min:30', 'max:240'],
            'patron' => ['nullable', 'string', 'max:100'],
            'precision' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'golpes' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'instrumento' => ['nullable', 'string', 'max:50'],
        ]);

        $estudiante = $this->estudiante($tareas);
        $habitos = app(HabitosService::class);
        $antes = $habitos->instantanea((int) $estudiante->id_estudiante);

        $resultado = $practicas->finalizar($estudiante, $idPractica, $metricas);

        return response()->json([
            'message' => $resultado['motivo'] ?? 'Practica registrada',
            'xp' => $resultado['xp'],
            'duracion_segundos' => $resultado['duracion_segundos'],
            'tope_alcanzado' => $resultado['tope_alcanzado'],
            'metricas_verificadas' => false,
            'minutos_hoy' => $habitos->minutosHoy((int) $estudiante->id_estudiante),
            'racha' => app(GamificationService::class)->resumen((int) $estudiante->id_estudiante)['racha_actual'],
        ] + $habitos->novedades($antes, $habitos->instantanea((int) $estudiante->id_estudiante)));
    }

    public function answerTheory(Request $request, int $idTeoria, TeoriaService $teoria, TareaService $tareas)
    {
        $validated = $request->validate(['respuesta' => ['required', 'string', 'max:150']]);

        $estudiante = $this->estudiante($tareas);
        $habitos = app(HabitosService::class);
        $antes = $habitos->instantanea((int) $estudiante->id_estudiante);
        $resultado = $teoria->responder($estudiante, $idTeoria, $validated['respuesta']);

        return response()->json($resultado + $habitos->novedades($antes, $habitos->instantanea((int) $estudiante->id_estudiante)));
    }

    /** Meta diaria de practica elegida por el estudiante (10, 15, 20, 30 o 45 minutos). */
    public function guardarMetaDiaria(Request $request, HabitosService $habitos, TareaService $tareas)
    {
        $validated = $request->validate([
            'minutos' => ['required', 'integer', 'in:'.implode(',', HabitosService::METAS)],
        ], [
            'minutos.in' => 'Elige una de las metas disponibles.',
        ]);

        $estudiante = $this->estudiante($tareas);
        $habitos->guardarMeta((int) $estudiante->id_estudiante, (int) $validated['minutos']);

        return response()->json([
            'message' => 'Meta diaria guardada: '.$validated['minutos'].' minutos.',
            'meta' => (int) $validated['minutos'],
            'semana' => $habitos->semanaActual((int) $estudiante->id_estudiante, (int) $validated['minutos']),
        ]);
    }

    public function submitTask(Request $request, int $idTarea, TareaService $tareas)
    {
        $validated = $request->validate([
            'comentario_estudiante' => ['nullable', 'string', 'max:1000'],
            'archivo' => [
                'nullable', 'file',
                'max:'.(int) config('msea.archivos.audio_max_kb'),
                'mimetypes:'.implode(',', (array) config('msea.archivos.audio_mimetypes')),
            ],
        ]);

        $entrega = $tareas->entregar(
            $this->estudiante($tareas),
            $idTarea,
            $validated['comentario_estudiante'] ?? null,
            $request->file('archivo')
        );

        return response()->json([
            'message' => 'Tarea entregada con exito',
            'entrega' => [
                'id' => $entrega->id_entrega,
                'estado' => $entrega->estado,
                'archivo_nombre' => $entrega->archivo_nombre,
            ],
        ]);
    }

    public function markNotificationsRead(Request $request)
    {
        $validated = $request->validate(['ids' => ['nullable', 'array', 'max:200'], 'ids.*' => ['integer']]);

        $query = DB::table('notificaciones')->where('id_usuario', (int) session('usuario_id'))->whereNull('leida_at');
        if (! empty($validated['ids'])) {
            $query->whereIn('id_notificacion', $validated['ids']);
        }

        return response()->json(['actualizadas' => $query->update(['leida_at' => now(), 'updated_at' => now()])]);
    }

    private function estudiante(TareaService $tareas): object
    {
        $estudiante = $tareas->estudianteDeUsuario((int) session('usuario_id'));
        abort_unless($estudiante, 404, 'No encontramos el perfil de estudiante');

        return $estudiante;
    }
}
