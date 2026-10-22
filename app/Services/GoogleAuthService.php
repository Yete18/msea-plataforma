<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Inicio de sesion con Google.
 *
 * 1. El navegador o la app obtienen un "ID token" firmado por Google.
 * 2. Aqui se valida con Google (firma, caducidad, emisor y que fue emitido
 *    para NUESTRO ID de cliente) y se exige correo verificado.
 * 3. Solo se entra a cuentas que YA existen en MSEA: el rol lo asigna la
 *    direccion, asi que Google no crea cuentas nuevas.
 *    - Primera vez: se vincula por correo y se guarda el "sub" de Google.
 *    - Despues: se identifica por el "sub" (aunque el correo cambie).
 */
class GoogleAuthService
{
    public const SIN_CUENTA = 'Tu cuenta de Google no está registrada en MSEA. Regístrate o pide a la dirección que te cree una cuenta con ese correo.';

    public const NO_DISPONIBLE = 'El inicio de sesión con Google no está configurado.';

    public const TOKEN_INVALIDO = 'No pudimos verificar tu cuenta de Google. Inténtalo de nuevo.';

    public function disponible(): bool
    {
        return ! empty(config('services.google.client_ids'));
    }

    /**
     * Devuelve los datos verificados del token o null si no es valido.
     *
     * @return array{sub:string,email:string,nombre:?string}|null
     */
    public function verificar(string $idToken): ?array
    {
        if (! $this->disponible() || strlen($idToken) > 4096) {
            return null;
        }

        try {
            // Por POST: el token no queda en la URL (ni en logs de proxies).
            $respuesta = Http::timeout(8)->acceptJson()->asForm()
                ->post((string) config('services.google.tokeninfo_url'), ['id_token' => $idToken]);
        } catch (Throwable $e) {
            // Solo la clase: el mensaje de error podria incluir la peticion.
            Log::warning('Google tokeninfo no disponible', ['error' => $e::class]);

            return null;
        }

        if (! $respuesta->successful()) {
            return null;
        }

        $c = $respuesta->json();
        $audiencias = (array) config('services.google.client_ids');
        $verificado = in_array((string) ($c['email_verified'] ?? ''), ['true', '1'], true) || ($c['email_verified'] ?? null) === true;

        $valido = is_array($c)
            && in_array((string) ($c['aud'] ?? ''), $audiencias, true)
            && in_array((string) ($c['iss'] ?? ''), ['accounts.google.com', 'https://accounts.google.com'], true)
            && (int) ($c['exp'] ?? 0) > time()
            && ! empty($c['sub'])
            && ! empty($c['email'])
            && $verificado;

        if (! $valido) {
            return null;
        }

        return [
            'sub' => (string) $c['sub'],
            'email' => AuthService::normalizarCorreo((string) $c['email']),
            'nombre' => $c['name'] ?? null,
        ];
    }

    /** Busca (y si hace falta vincula) la cuenta MSEA de esa identidad de Google. */
    public function usuarioPara(array $google): ?Usuario
    {
        return DB::transaction(function () use ($google) {
            $usuario = Usuario::query()->with('rol')->where('google_sub', $google['sub'])->first();

            if (! $usuario) {
                $usuario = Usuario::query()->with('rol')->where('correo', $google['email'])->lockForUpdate()->first();

                // Correo ya vinculado a OTRA cuenta de Google: no se reemplaza.
                if (! $usuario || ($usuario->google_sub && $usuario->google_sub !== $google['sub'])) {
                    return null;
                }

                $cambios = ['google_sub' => $google['sub'], 'correo_verificado_at' => now()];

                // Cuenta del registro publico con correo nunca verificado: quien
                // la creo pudo usar un correo ajeno. Al demostrar el dueno real
                // (Google entrega el correo verificado) se anula la contrasena
                // anterior y se cierran sus sesiones web (cambia la huella) y moviles.
                if ($usuario->origen === 'registro' && $usuario->correo_verificado_at === null) {
                    $cambios['contrasena'] = Hash::make(Str::random(64));
                    app(MobileTokenService::class)->revocarTodos((int) $usuario->id_usuario);
                    app(AuditLogger::class)->registrar('google_reclama_cuenta', (int) $usuario->id_usuario);
                }

                $usuario->forceFill($cambios)->save();
            }

            if ($usuario->activo === false) {
                return null;
            }

            $usuario->setAttribute('rol_nombre', $usuario->rol?->nombre);

            return $usuario;
        });
    }
}
