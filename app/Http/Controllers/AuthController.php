<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AuthenticateSession;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Usuario;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Services\GoogleAuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public const MENSAJE_CREDENCIALES = 'Correo electrónico o contraseña incorrectos.';

    public function login(LoginRequest $request, AuthService $authService, AuditLogger $audit)
    {
        $validated = $request->validated();

        $usuario = $authService->autenticar($validated['correo'], $validated['contrasena']);

        if (! $usuario) {
            $audit->registrar('login_fallido', null, ['correo' => AuditLogger::enmascararCorreo($validated['correo'])]);

            return back()
                ->withInput($request->only('correo'))
                ->with('error', self::MENSAJE_CREDENCIALES);
        }

        // UX-07: ya no se elige el rol; cada cuenta entra a su propio panel.

        $request->session()->regenerate();
        $this->iniciarSesion($request, $usuario->fresh(['rol']));

        Usuario::query()->whereKey($usuario->id_usuario)->update(['ultimo_ingreso_at' => now()]);
        $audit->registrar('login', $usuario->id_usuario, ['rol' => $usuario->rol_nombre]);

        return redirect(self::rutaInicio($usuario->rol_nombre));
    }

    /** Inicio de sesion con Google (boton de Google Identity Services en /login). */
    public function loginGoogle(Request $request, GoogleAuthService $google, AuditLogger $audit)
    {
        $validated = $request->validate(['credential' => ['required', 'string', 'max:4096']]);

        if (! $google->disponible()) {
            return response()->json(['message' => GoogleAuthService::NO_DISPONIBLE], 422);
        }

        $datos = $google->verificar($validated['credential']);
        if (! $datos) {
            $audit->registrar('login_google_fallido', null, ['motivo' => 'token_invalido']);

            return response()->json(['message' => GoogleAuthService::TOKEN_INVALIDO], 422);
        }

        $usuario = $google->usuarioPara($datos);
        if (! $usuario) {
            $audit->registrar('login_google_fallido', null, ['correo' => AuditLogger::enmascararCorreo($datos['email'])]);

            return response()->json(['message' => GoogleAuthService::SIN_CUENTA], 422);
        }

        $request->session()->regenerate();
        $this->iniciarSesion($request, $usuario->fresh(['rol']));
        Usuario::query()->whereKey($usuario->id_usuario)->update(['ultimo_ingreso_at' => now()]);
        $audit->registrar('login_google', $usuario->id_usuario, ['rol' => $usuario->rol_nombre]);

        return response()->json(['redirect' => url(self::rutaInicio($usuario->rol_nombre))]);
    }

    public function register(RegisterRequest $request, AuthService $authService, AuditLogger $audit)
    {
        $validated = $request->validated();
        $idUsuario = $authService->registrarEstudiante($validated);

        if (! $idUsuario) {
            return back()->withInput()->with('error', 'Rol no válido');
        }

        $request->session()->regenerate();
        $this->iniciarSesion($request, Usuario::query()->with('rol')->findOrFail($idUsuario));
        $audit->registrar('registro_estudiante', $idUsuario);

        return redirect('/dashboard-estudiante');
    }

    public function logout(Request $request, AuditLogger $audit)
    {
        $audit->registrar('logout', $request->session()->get('usuario_id'));

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public static function rutaInicio(?string $rol): string
    {
        return match ($rol) {
            'estudiante' => '/dashboard-estudiante',
            'profesor' => '/dashboard-profesor',
            'director' => '/dashboard-admin',
            'padre' => '/dashboard-padre',
            default => '/',
        };
    }

    private function iniciarSesion(Request $request, Usuario $usuario): void
    {
        $request->session()->put([
            'usuario_id' => $usuario->id_usuario,
            'nombre' => $usuario->nombres,
            'apellido_paterno' => $usuario->apellido_paterno,
            'correo' => $usuario->correo,
            'rol' => $usuario->rol?->nombre,
            // Huella de la contrasena: si cambia, las demas sesiones se cierran.
            'huella_credencial' => AuthenticateSession::huella((string) $usuario->contrasena),
        ]);
    }
}
