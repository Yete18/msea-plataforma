<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Services\GamificationService;
use App\Services\GoogleAuthService;
use App\Services\MobileTokenService;
use App\Services\PadreDashboardService;
use App\Services\PracticaService;
use App\Services\ProfilePhotoService;
use App\Services\TareaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileController extends Controller
{
    public function login(Request $request, AuthService $auth, MobileTokenService $tokens, AuditLogger $audit)
    {
        $request->merge(['correo' => AuthService::normalizarCorreo($request->input('correo'))]);

        $validated = $request->validate([
            'correo' => ['required', 'email', 'max:100'],
            'contrasena' => ['required', 'string', 'max:255'],
            // UX-07: el rol ya no se elige; manda el de la cuenta. Se acepta (y se
            // ignora) para que las versiones anteriores de la app sigan entrando.
            'rol' => ['nullable', 'in:director,admin,profesor,estudiante,padre'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $usuario = $auth->autenticar($validated['correo'], $validated['contrasena']);

        if (! $usuario) {
            $audit->registrar('login_movil_fallido', $usuario?->id_usuario, ['correo' => AuditLogger::enmascararCorreo($validated['correo'])]);

            // Mismo mensaje para correo inexistente o contrasena incorrecta.
            return response()->json(['message' => AuthController::MENSAJE_CREDENCIALES], 422);
        }

        $token = $tokens->emitir($usuario, $validated['device_name'] ?? 'Flutter', $request->ip());
        $audit->registrar('login_movil', $usuario->id_usuario);

        return response()->json([
            'token' => $token,
            'expira_en_dias' => (int) config('msea.api.token_dias'),
            'usuario' => $this->usuarioPayload($usuario),
        ]);
    }

    public function loginGoogle(Request $request, GoogleAuthService $google, MobileTokenService $tokens, AuditLogger $audit)
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string', 'max:4096'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        if (! $google->disponible()) {
            return response()->json(['message' => GoogleAuthService::NO_DISPONIBLE], 422);
        }

        $datos = $google->verificar($validated['id_token']);
        $usuario = $datos ? $google->usuarioPara($datos) : null;

        if (! $usuario) {
            $audit->registrar('login_movil_google_fallido', null, $datos ? ['correo' => AuditLogger::enmascararCorreo($datos['email'])] : []);

            return response()->json(['message' => $datos ? GoogleAuthService::SIN_CUENTA : GoogleAuthService::TOKEN_INVALIDO], 422);
        }

        $token = $tokens->emitir($usuario, $validated['device_name'] ?? 'Flutter', $request->ip());
        $audit->registrar('login_movil_google', $usuario->id_usuario);

        return response()->json([
            'token' => $token,
            'expira_en_dias' => (int) config('msea.api.token_dias'),
            'usuario' => $this->usuarioPayload($usuario),
        ]);
    }

    public function me(Request $request, GamificationService $gamificacion, TareaService $tareas, PadreDashboardService $padres)
    {
        $usuario = $this->usuario($request);
        $rol = $usuario->rol?->nombre;

        $dashboard = match ($rol) {
            'estudiante' => $this->estudiantePayload($usuario, $gamificacion, $tareas),
            'profesor' => $this->profesorPayload($usuario),
            'padre' => ['estudiantes' => $padres->hijos($usuario->id_usuario)->map(fn ($h) => collect($h)->only([
                'id', 'nombre', 'nivel', 'xp_total', 'racha', 'posicion', 'total_minutos', 'tareas',
            ]))->values()],
            'director' => [
                'totalProfesores' => DB::table('profesores')->count(),
                'totalEstudiantes' => DB::table('estudiantes')->count(),
                'totalTareas' => DB::table('tareas')->count(),
                'totalPracticas' => DB::table('practicas_autonomas')->where('estado', 'finalizada')->count(),
            ],
            default => null,
        };

        return response()->json(['usuario' => $this->usuarioPayload($usuario), 'dashboard' => $dashboard]);
    }

    public function tasks(Request $request, TareaService $tareas)
    {
        $estudiante = $this->estudiante($request, $tareas);

        $lista = $tareas->tareasVisiblesPara($estudiante)
            ->leftJoin('entregas_tareas as et', function ($join) use ($estudiante) {
                $join->on('et.id_tarea', '=', 't.id_tarea')->where('et.id_estudiante', '=', $estudiante->id_estudiante);
            })
            ->orderByDesc('t.fecha_creacion')
            ->limit(50)
            ->get(['t.id_tarea', 't.titulo', 't.descripcion', 't.fecha_limite', 't.xp_recompensa', 't.id_elenco',
                'et.estado', 'et.calificacion', 'et.comentario_profesor'])
            ->map(fn ($t) => [
                'id' => (int) $t->id_tarea,
                'titulo' => $t->titulo,
                'descripcion' => $t->descripcion,
                'fecha_limite' => $t->fecha_limite,
                'xp' => (int) $t->xp_recompensa,
                'grupal' => (bool) $t->id_elenco,
                'estado' => $t->estado ?: 'pendiente',
                'calificacion' => $t->calificacion !== null ? (float) $t->calificacion : null,
                'comentario_profesor' => $t->comentario_profesor,
            ]);

        return response()->json(['tareas' => $lista]);
    }

    public function submitTask(Request $request, int $idTarea, TareaService $tareas)
    {
        $validated = $request->validate([
            'comentario_estudiante' => ['nullable', 'string', 'max:1000'],
            'archivo' => ['nullable', 'file', 'max:'.(int) config('msea.archivos.audio_max_kb'),
                'mimetypes:'.implode(',', (array) config('msea.archivos.audio_mimetypes'))],
        ]);

        $entrega = $tareas->entregar($this->estudiante($request, $tareas), $idTarea, $validated['comentario_estudiante'] ?? null, $request->file('archivo'));

        return response()->json(['message' => 'Tarea entregada', 'estado' => $entrega->estado]);
    }

    public function startPractice(Request $request, PracticaService $practicas, TareaService $tareas)
    {
        $validated = $request->validate([
            'tipo' => ['required', 'string', 'in:'.implode(',', (array) config('msea.practica.tipos'))],
            'id_ejercicio' => ['nullable', 'integer', 'exists:ejercicios,id_ejercicio'],
        ]);

        $practica = $practicas->iniciar($this->estudiante($request, $tareas), $validated['tipo'], $validated['id_ejercicio'] ?? null);

        return response()->json(['id_practica' => $practica->id_practica_autonoma, 'iniciada_at' => $practica->iniciada_at->toIso8601String()], 201);
    }

    public function finishPractice(Request $request, int $idPractica, PracticaService $practicas, TareaService $tareas)
    {
        $metricas = $request->validate([
            'bpm' => ['nullable', 'integer', 'min:30', 'max:240'],
            'precision' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'nota_detectada' => ['nullable', 'string', 'max:10'],
        ]);

        $r = $practicas->finalizar($this->estudiante($request, $tareas), $idPractica, $metricas);

        return response()->json([
            'message' => $r['motivo'] ?? 'Practica registrada',
            'xp' => $r['xp'],
            'duracion_segundos' => $r['duracion_segundos'],
            'tope_alcanzado' => $r['tope_alcanzado'],
        ]);
    }

    public function notifications(Request $request)
    {
        $usuario = $this->usuario($request);

        return response()->json([
            'notificaciones' => DB::table('notificaciones')->where('id_usuario', $usuario->id_usuario)
                ->orderByDesc('created_at')->limit(30)
                ->get(['id_notificacion as id', 'titulo', 'mensaje', 'tipo', 'leida_at', 'created_at']),
        ]);
    }

    public function markNotificationsRead(Request $request)
    {
        $usuario = $this->usuario($request);

        return response()->json([
            'actualizadas' => DB::table('notificaciones')->where('id_usuario', $usuario->id_usuario)->whereNull('leida_at')
                ->update(['leida_at' => now(), 'updated_at' => now()]),
        ]);
    }

    public function sessions(Request $request, MobileTokenService $tokens)
    {
        $usuario = $this->usuario($request);

        return response()->json(['sesiones' => $tokens->sesiones($usuario->id_usuario)->map(fn ($s) => [
            'id' => $s->id,
            'dispositivo' => $s->device_name,
            'ultimo_uso' => $s->last_used_at?->toIso8601String(),
            'expira' => $s->expires_at?->toIso8601String(),
            'actual' => $s->id === $usuario->token_actual_id,
        ])]);
    }

    public function revokeSession(Request $request, int $idToken, MobileTokenService $tokens, AuditLogger $audit)
    {
        $usuario = $this->usuario($request);
        abort_unless($tokens->revocarPorId($usuario->id_usuario, $idToken), 404);
        $audit->registrar('sesion_movil_revocada', $usuario->id_usuario);

        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function logout(Request $request, MobileTokenService $tokens)
    {
        $tokens->revocar($request->bearerToken());

        return response()->json(['message' => 'Sesión móvil cerrada']);
    }

    public function logoutAll(Request $request, MobileTokenService $tokens, AuditLogger $audit)
    {
        $usuario = $this->usuario($request);
        $tokens->revocarTodos($usuario->id_usuario);
        $audit->registrar('sesiones_moviles_revocadas', $usuario->id_usuario);

        return response()->json(['message' => 'Se cerraron todas las sesiones moviles']);
    }

    // ---------------------------------------------------------------------

    private function usuario(Request $request): Usuario
    {
        return $request->attributes->get('usuario_movil');
    }

    private function estudiante(Request $request, TareaService $tareas): object
    {
        $estudiante = $tareas->estudianteDeUsuario($this->usuario($request)->id_usuario);
        abort_unless($estudiante, 404, 'Perfil de estudiante no encontrado');

        return $estudiante;
    }

    private function usuarioPayload(Usuario $usuario): array
    {
        return [
            'id' => $usuario->id_usuario,
            'nombre' => trim($usuario->nombres.' '.$usuario->apellido_paterno),
            'correo' => $usuario->correo,
            'rol' => $usuario->rol?->nombre,
            'foto' => app(ProfilePhotoService::class)->publicValue($usuario->foto, (int) $usuario->id_usuario),
        ];
    }

    private function estudiantePayload(Usuario $usuario, GamificationService $gamificacion, TareaService $tareas): ?array
    {
        $estudiante = $tareas->estudianteDeUsuario($usuario->id_usuario);
        if (! $estudiante) {
            return null;
        }

        $resumen = $gamificacion->resumen((int) $estudiante->id_estudiante);
        $pendientes = $tareas->tareasVisiblesPara($estudiante)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('entregas_tareas as et')
                ->whereColumn('et.id_tarea', 't.id_tarea')
                ->where('et.id_estudiante', $estudiante->id_estudiante)
                ->whereIn('et.estado', ['entregada', 'calificada']))
            ->count();

        return $resumen + [
            'tareas_pendientes' => $pendientes,
            'xp_practica_hoy' => $gamificacion->xpDeFuenteHoy((int) $estudiante->id_estudiante, 'practica'),
            'tope_diario_practica' => (int) config('msea.xp.tope_diario_practica'),
        ];
    }

    private function profesorPayload(Usuario $usuario): array
    {
        $profesor = DB::table('profesores')->where('id_usuario', $usuario->id_usuario)->first();

        if (! $profesor) {
            return ['totalAlumnos' => 0, 'tareas' => 0, 'entregasPendientes' => 0];
        }

        return [
            'totalAlumnos' => DB::table('estudiantes')->where('id_profesor', $profesor->id_profesor)->count(),
            'tareas' => DB::table('tareas')->where('id_profesor', $profesor->id_profesor)->count(),
            'entregasPendientes' => DB::table('entregas_tareas as et')->join('tareas as t', 't.id_tarea', '=', 'et.id_tarea')
                ->join('estudiantes as e', 'e.id_estudiante', '=', 'et.id_estudiante')
                ->where('t.id_profesor', $profesor->id_profesor)->where('e.id_profesor', $profesor->id_profesor)
                ->where('et.estado', 'entregada')->count(),
        ];
    }
}
