<?php

namespace App\Services;

use App\Mail\CodigoRecuperacionMail;
use App\Models\PasswordResetCode;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PasswordResetService
{
    public function __construct(
        private readonly CuentaSeguridadService $cuenta,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Genera y envia un codigo solo si la cuenta existe y esta activa. La
     * respuesta al usuario es siempre la misma; si el proveedor de correo falla
     * se registra el error (sin el codigo) y no se muestra un error 500.
     */
    public function enviarCodigoSiUsuarioExiste(string $correo): void
    {
        $correo = AuthService::normalizarCorreo($correo);
        $usuario = Usuario::query()->where('correo', $correo)->first();

        if (! $usuario || $usuario->activo === false) {
            return;
        }

        // Cuenta bloqueada por demasiados fallos en 24 h: no se emiten codigos
        // nuevos (cada codigo nuevo reiniciaba el contador de intentos).
        if ($this->bloqueadaPorIntentos($correo)) {
            $this->audit->registrar('recuperacion_bloqueada', $usuario->id_usuario);

            return;
        }

        $codigo = (string) random_int(100000, 999999);
        $minutos = (int) config('msea.recuperacion.minutos_validez', 15);

        DB::transaction(function () use ($correo, $codigo, $minutos) {
            PasswordResetCode::query()
                ->where('correo', $correo)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            PasswordResetCode::query()->create([
                'correo' => $correo,
                'codigo' => Hash::make($codigo),
                'expires_at' => now()->addMinutes($minutos),
                'created_at' => now(),
                'intentos' => 0,
            ]);
        });

        // SEC-07: con el mailer "log" el codigo quedaria escrito en el log del
        // servidor. En produccion no se "envia" asi: hay que configurar un SMTP.
        if (app()->isProduction() && in_array(config('mail.default'), ['log', 'array'], true)) {
            Log::critical('MSEA: correo de recuperación no enviado porque MAIL_MAILER no es un servicio real. Configura SMTP en .env.');
            $this->audit->registrar('recuperacion_sin_correo', $usuario->id_usuario);

            return;
        }

        try {
            $mail = new CodigoRecuperacionMail($codigo, $minutos);

            if (config('msea.correo.usar_cola')) {
                Mail::to($correo)->queue($mail);
            } else {
                Mail::to($correo)->send($mail);
            }
        } catch (Throwable $e) {
            Log::error('MSEA: no se pudo enviar el correo de recuperación.', [
                'usuario' => $usuario->id_usuario,
                'error' => $e->getMessage(),
            ]);
        }

        $this->audit->registrar('recuperacion_solicitada', $usuario->id_usuario);
    }

    /**
     * Devuelve el id del codigo si es correcto. Cada fallo suma un intento al
     * codigo vigente; al llegar al maximo, el codigo queda invalidado aunque el
     * atacante cambie de IP.
     */
    public function verificarCodigo(string $correo, string $codigo): ?int
    {
        $correo = AuthService::normalizarCorreo($correo);
        $maximo = (int) config('msea.recuperacion.max_intentos_por_codigo', 5);

        return DB::transaction(function () use ($correo, $codigo, $maximo) {
            $vigente = PasswordResetCode::query()
                ->where('correo', $correo)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $vigente || $this->bloqueadaPorIntentos($correo)) {
                return null;
            }

            if ($vigente->intentos < $maximo && Hash::check($codigo, $vigente->codigo)) {
                return $vigente->id;
            }

            $vigente->intentos = $vigente->intentos + 1;
            if ($vigente->intentos >= $maximo) {
                $vigente->used_at = now();
            }
            $vigente->save();

            return null;
        });
    }

    /**
     * Fallos sumados de TODOS los codigos de la cuenta en las ultimas 24 horas.
     * Sin este tope, pedir codigos nuevos permitia ~2.000 intentos al dia.
     */
    public function bloqueadaPorIntentos(string $correo): bool
    {
        $maximo = (int) config('msea.recuperacion.max_intentos_por_dia', 20);

        return (int) PasswordResetCode::query()
            ->where('correo', $correo)
            ->where('created_at', '>=', now()->subDay())
            ->sum('intentos') >= $maximo;
    }

    public function resetPassword(string $correo, int $codigoId, string $contrasena): bool
    {
        $correo = AuthService::normalizarCorreo($correo);

        $idUsuario = DB::transaction(function () use ($correo, $codigoId) {
            $codigoActualizado = PasswordResetCode::query()
                ->whereKey($codigoId)
                ->where('correo', $correo)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update(['used_at' => now()]);

            if ($codigoActualizado !== 1) {
                return null;
            }

            return Usuario::query()->where('correo', $correo)->value('id_usuario');
        });

        if (! $idUsuario) {
            return false;
        }

        $this->cuenta->establecerContrasena((int) $idUsuario, $contrasena, 'recuperacion_completada');

        return true;
    }
}
