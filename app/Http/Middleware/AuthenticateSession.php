<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige una sesion valida y la contrasta en cada peticion con la base de datos:
 * - usuario desactivado o eliminado  -> sesion cerrada
 * - rol cambiado por el director     -> sesion cerrada
 * - contrasena cambiada en otro lado -> sesion cerrada (si la sesion tiene huella)
 */
class AuthenticateSession
{
    public static function huella(string $hashContrasena): string
    {
        return substr(hash('sha256', $hashContrasena.'|'.config('app.key')), 0, 32);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();

        if (! $session->has('usuario_id') || ! $session->has('rol')) {
            return $this->rechazar($request, 'Inicia sesión para continuar');
        }

        $usuario = DB::table('usuarios as u')
            ->join('roles as r', 'r.id_rol', '=', 'u.id_rol')
            ->where('u.id_usuario', (int) $session->get('usuario_id'))
            ->select(['u.id_usuario', 'u.activo', 'u.contrasena', 'r.nombre as rol'])
            ->first();

        $huella = $session->get('huella_credencial');

        $valida = $usuario
            && (bool) $usuario->activo
            && $usuario->rol === $session->get('rol')
            && ($huella === null || hash_equals($huella, self::huella((string) $usuario->contrasena)));

        if (! $valida) {
            $session->invalidate();
            $session->regenerateToken();

            return $this->rechazar($request, 'Tu sesión ya no es válida. Inicia sesión nuevamente.');
        }

        $request->attributes->set('usuario_actual_id', (int) $usuario->id_usuario);

        return $next($request);
    }

    private function rechazar(Request $request, string $mensaje): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        return redirect('/login')->with('error', $mensaje);
    }
}
