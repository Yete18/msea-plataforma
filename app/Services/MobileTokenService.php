<?php

namespace App\Services;

use App\Models\MobileApiToken;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Tokens de la app movil.
 *
 * Se mantiene el mecanismo propio (en lugar de Sanctum) porque el modelo de
 * usuarios usa claves y tablas no estandar (usuarios.id_usuario) y Sanctum
 * exigiria agregar una dependencia y la tabla personal_access_tokens. El
 * mecanismo cumple lo mismo: token aleatorio de 64 caracteres, en la BD solo
 * su SHA-256, expiracion, limite de sesiones y revocacion.
 */
class MobileTokenService
{
    public function emitir(Usuario $usuario, string $dispositivo, ?string $ip): string
    {
        $plano = Str::random(64);

        MobileApiToken::query()->create([
            'id_usuario' => $usuario->id_usuario,
            'token_hash' => hash('sha256', $plano),
            'device_name' => mb_substr($dispositivo, 0, 120),
            'last_used_at' => now(),
            'expires_at' => now()->addDays((int) config('msea.api.token_dias', 30)),
            'ip' => $ip,
        ]);

        $this->recortarSesiones((int) $usuario->id_usuario);

        return $plano;
    }

    /** Devuelve el usuario dueno de un token vigente o null. */
    public function resolver(?string $plano): ?Usuario
    {
        if (! $plano || strlen($plano) > 200) {
            return null;
        }

        $registro = MobileApiToken::query()->where('token_hash', hash('sha256', $plano))->first();

        if (! $registro) {
            return null;
        }

        if ($registro->expires_at && $registro->expires_at->isPast()) {
            $registro->delete();

            return null;
        }

        $usuario = Usuario::query()->with('rol')->find($registro->id_usuario);

        if (! $usuario || $usuario->activo === false) {
            return null;
        }

        if (! $registro->last_used_at || $registro->last_used_at->lt(now()->subMinute())) {
            $registro->forceFill(['last_used_at' => now()])->save();
        }

        $usuario->setAttribute('token_actual_id', $registro->id);

        return $usuario;
    }

    public function revocar(?string $plano): void
    {
        if ($plano) {
            MobileApiToken::query()->where('token_hash', hash('sha256', $plano))->delete();
        }
    }

    public function revocarPorId(int $idUsuario, int $idToken): bool
    {
        return MobileApiToken::query()->where('id_usuario', $idUsuario)->whereKey($idToken)->delete() > 0;
    }

    public function revocarTodos(int $idUsuario): int
    {
        return MobileApiToken::query()->where('id_usuario', $idUsuario)->delete();
    }

    public function sesiones(int $idUsuario): Collection
    {
        return MobileApiToken::query()
            ->where('id_usuario', $idUsuario)
            ->orderByDesc('last_used_at')
            ->get(['id', 'device_name', 'last_used_at', 'expires_at', 'created_at']);
    }

    private function recortarSesiones(int $idUsuario): void
    {
        $maximo = max(1, (int) config('msea.api.max_sesiones_por_usuario', 5));

        $sobrantes = MobileApiToken::query()
            ->where('id_usuario', $idUsuario)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->skip($maximo)
            ->take(1000)
            ->pluck('id');

        if ($sobrantes->isNotEmpty()) {
            MobileApiToken::query()->whereIn('id', $sobrantes)->delete();
        }
    }
}
