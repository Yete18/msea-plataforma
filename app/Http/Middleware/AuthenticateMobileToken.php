<?php

namespace App\Http\Middleware;

use App\Services\MobileTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticacion por token Bearer de la app movil (vigente, no revocado,
 * usuario activo). Deja el usuario en $request->attributes('usuario_movil').
 */
class AuthenticateMobileToken
{
    public function __construct(private readonly MobileTokenService $tokens) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $this->tokens->resolver($request->bearerToken());

        if (! $usuario) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        if ($roles && ! in_array($usuario->rol?->nombre, $roles, true)) {
            return response()->json(['message' => 'No tienes permiso para esta acción'], 403);
        }

        $request->attributes->set('usuario_movil', $usuario);

        return $next($request);
    }
}
