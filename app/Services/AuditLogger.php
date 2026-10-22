<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Bitacora de seguridad. Nunca recibe ni guarda contrasenas, codigos ni tokens:
 * cualquier clave sensible se elimina antes de escribir.
 */
class AuditLogger
{
    private const CLAVES_PROHIBIDAS = [
        'contrasena', 'contrasena_actual', 'contrasena_confirmation', 'password',
        'codigo', 'token', 'token_hash', 'foto',
    ];

    public function registrar(string $accion, ?int $idUsuario = null, array $detalle = []): void
    {
        try {
            $request = request();

            Auditoria::query()->create([
                'id_usuario' => $idUsuario,
                'accion' => mb_substr($accion, 0, 60),
                'detalle' => $detalle ? Arr::except($detalle, self::CLAVES_PROHIBIDAS) : null,
                'ip' => $request?->ip(),
                'user_agent' => mb_substr((string) $request?->userAgent(), 0, 255) ?: null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // La auditoria nunca debe tumbar la peticion principal.
            report($e);
        }
    }

    /** Oculta un correo para bitacora: "ju***@dominio.com". */
    public static function enmascararCorreo(string $correo): string
    {
        [$usuario, $dominio] = array_pad(explode('@', $correo, 2), 2, '');

        return mb_substr($usuario, 0, 2).'***@'.$dominio;
    }
}
