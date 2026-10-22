<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $currentRole = $this->normalizeRole((string) $request->session()->get('rol'));
        $allowedRoles = array_map(fn (string $role) => $this->normalizeRole($role), $roles);

        if (! $currentRole || ! in_array($currentRole, $allowedRoles, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'No tienes permiso para acceder a este recurso.',
                ], 403);
            }

            return redirect('/login')->with('error', 'No tienes permiso para acceder a esa sección');
        }

        return $next($request);
    }

    private function normalizeRole(string $role): string
    {
        return match (strtolower(trim($role))) {
            'admin' => 'director',
            default => strtolower(trim($role)),
        };
    }
}
