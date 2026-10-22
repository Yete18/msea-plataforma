<?php

namespace App\Http\Middleware;

use App\Http\Controllers\AuthController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si ya hay una sesion abierta, las pantallas de login y registro llevan al panel.
 * Evita que al volver con la flecha del navegador se reenvie un formulario viejo.
 */
class RedirigirSiAutenticado
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();

        if ($session->has('usuario_id') && $session->has('rol')) {
            return redirect(AuthController::rutaInicio($session->get('rol')));
        }

        return $next($request);
    }
}
