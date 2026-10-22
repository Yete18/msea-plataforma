<?php

namespace App\Services;

use App\Http\Middleware\AuthenticateSession;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Cambios sensibles de cuenta: correo y contrasena.
 * Ambos exigen la contrasena actual y revocan los tokens moviles.
 */
class CuentaSeguridadService
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly MobileTokenService $tokens,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Lanza ValidationException si el correo cambia sin contrasena actual valida.
     * Devuelve true si el correo cambia.
     */
    public function exigirReautenticacionSiCambiaCorreo(int $idUsuario, string $correoNuevo, ?string $contrasenaActual): bool
    {
        $usuario = Usuario::query()->findOrFail($idUsuario);
        $correoNuevo = AuthService::normalizarCorreo($correoNuevo);

        if ($correoNuevo === AuthService::normalizarCorreo($usuario->correo)) {
            return false;
        }

        if (! $contrasenaActual || ! $this->authService->contrasenaValida($contrasenaActual, $usuario)) {
            throw ValidationException::withMessages([
                'contrasena_actual' => 'Para cambiar el correo escribe tu contraseña actual.',
            ]);
        }

        return true;
    }

    public function despuesDeCambiarCorreo(int $idUsuario, string $correoAnterior, string $correoNuevo): void
    {
        $this->tokens->revocarTodos($idUsuario);
        $this->audit->registrar('cambio_correo', $idUsuario, [
            'anterior' => AuditLogger::enmascararCorreo($correoAnterior),
            'nuevo' => AuditLogger::enmascararCorreo($correoNuevo),
        ]);
    }

    public function cambiarContrasena(int $idUsuario, string $actual, string $nueva): void
    {
        $usuario = Usuario::query()->findOrFail($idUsuario);

        if (! $this->authService->contrasenaValida($actual, $usuario)) {
            throw ValidationException::withMessages([
                'contrasena_actual' => 'La contraseña actual no es correcta.',
            ]);
        }

        $this->establecerContrasena($idUsuario, $nueva, 'cambio_contrasena');
    }

    /**
     * Fija una contrasena nueva, revoca tokens moviles y deja constancia.
     * Las demas sesiones web se cierran solas porque cambia la huella.
     */
    public function establecerContrasena(int $idUsuario, string $nueva, string $motivo): void
    {
        Usuario::query()->whereKey($idUsuario)->update(['contrasena' => Hash::make($nueva)]);
        $this->tokens->revocarTodos($idUsuario);
        $this->audit->registrar($motivo, $idUsuario);

        // Mantener viva la sesion actual si es del mismo usuario.
        if (request()?->hasSession() && (int) session('usuario_id') === $idUsuario) {
            $hash = (string) Usuario::query()->whereKey($idUsuario)->value('contrasena');
            session(['huella_credencial' => AuthenticateSession::huella($hash)]);
        }
    }
}
