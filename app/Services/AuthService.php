<?php

namespace App\Services;

use App\Models\Estudiante;
use App\Models\Instrumento;
use App\Models\Rol;
use App\Models\Seccion;
use App\Models\Usuario;
use App\Models\UsuarioInstrumento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public static function normalizarCorreo(?string $correo): string
    {
        return mb_strtolower(trim((string) $correo));
    }

    public function findUsuarioConRol(string $correo): ?Usuario
    {
        $usuario = Usuario::query()
            ->with('rol')
            ->where('correo', self::normalizarCorreo($correo))
            ->first();

        if ($usuario) {
            $usuario->setAttribute('rol_nombre', $usuario->rol?->nombre);
        }

        return $usuario;
    }

    /**
     * Intenta autenticar. Devuelve el usuario solo si el correo existe, la
     * contrasena es correcta y la cuenta esta activa. Cualquier otro caso
     * devuelve null, sin distinguir el motivo (evita enumerar cuentas).
     */
    public function autenticar(string $correo, string $contrasena): ?Usuario
    {
        $usuario = $this->findUsuarioConRol($correo);

        if (! $usuario) {
            // Mismo costo que verificar un hash real: el tiempo de respuesta no
            // delata si el correo existe.
            Hash::make($contrasena);

            return null;
        }

        if (! $this->contrasenaValida($contrasena, $usuario)) {
            return null;
        }

        if ($usuario->activo === false) {
            return null;
        }

        return $usuario;
    }

    /**
     * Verifica la contrasena.
     *
     * - Si lo guardado es un hash reconocido (bcrypt, argon), SOLO se acepta
     *   Hash::check. Escribir el hash literal como contrasena no funciona.
     * - Una contrasena guardada en texto plano ya no se acepta (ARQ-04).
     */
    public function contrasenaValida(string $contrasenaIngresada, object $usuario): bool
    {
        $contrasenaGuardada = (string) $usuario->contrasena;

        if ($contrasenaGuardada === '' || $contrasenaIngresada === '') {
            return false;
        }

        if (self::pareceHash($contrasenaGuardada)) {
            if (! Hash::check($contrasenaIngresada, $contrasenaGuardada)) {
                return false;
            }

            if (Hash::needsRehash($contrasenaGuardada)) {
                $this->actualizarContrasena((int) $usuario->id_usuario, $contrasenaIngresada);
            }

            return true;
        }

        // Ya no se aceptan contrasenas guardadas en texto plano: la migracion
        // 2026_10_17_000003 las convirtio a hash conservando su valor.
        return false;
    }

    public static function pareceHash(string $valor): bool
    {
        if (preg_match('/^\$(2[abxy]|argon2i|argon2id)\$/', $valor)) {
            return true;
        }

        return (password_get_info($valor)['algo'] ?? null) !== null;
    }

    public function registrarEstudiante(array $validated): int|false
    {
        $rol = Rol::query()->where('nombre', $validated['rol'])->first();

        if (! $rol) {
            return false;
        }

        return DB::transaction(function () use ($validated, $rol) {
            $usuario = Usuario::query()->create([
                'correo' => self::normalizarCorreo($validated['correo']),
                'contrasena' => Hash::make($validated['contrasena']),
                'nombres' => $validated['nombres'],
                'apellido_paterno' => $validated['apellido_paterno'],
                'apellido_materno' => $validated['apellido_materno'] ?? null,
                'id_rol' => $rol->id_rol,
                // Registro publico: el correo no esta verificado (ver GoogleAuthService::usuarioPara).
                'origen' => 'registro',
            ]);

            if ($validated['rol'] === 'estudiante') {
                Estudiante::query()->create([
                    'id_usuario' => $usuario->id_usuario,
                    'fecha_ingreso' => now()->toDateString(),
                    // Nivel elegido al registrarse (solo si existe); si no, "General".
                    'id_seccion' => ! empty($validated['nivel'])
                        ? (Seccion::query()->where('nombre', $validated['nivel'])->value('id_seccion') ?? $this->obtenerOCrearSeccionGeneral())
                        : $this->obtenerOCrearSeccionGeneral(),
                ]);

                $idInstrumento = ! empty($validated['instrumento']) ? self::idInstrumentoExistente($validated['instrumento']) : null;
                if ($idInstrumento !== null) {
                    UsuarioInstrumento::query()->firstOrCreate([
                        'id_usuario' => $usuario->id_usuario,
                        'id_instrumento' => $idInstrumento,
                    ]);
                }
            }

            return $usuario->id_usuario;
        });
    }

    public static function normalizarInstrumento(string $instrumento): string
    {
        return match (mb_strtolower(trim($instrumento))) {
            'violin', 'violín' => 'Violin',
            'viola' => 'Viola',
            'chelo', 'cello', 'violonchelo', 'violoncello' => 'Violonchelo',
            'bajo', 'contrabajo' => 'Contrabajo',
            default => ucfirst(trim($instrumento)),
        };
    }

    private function obtenerOCrearSeccionGeneral(): int
    {
        $seccion = Seccion::query()->firstOrCreate([
            'nombre' => 'General',
        ]);

        return $seccion->id_seccion;
    }

    /** Id del instrumento del catalogo (sin distinguir mayusculas), o null si no existe. */
    public static function idInstrumentoExistente(string $instrumento): ?int
    {
        $id = Instrumento::query()
            ->whereRaw('lower(nombre) = ?', [mb_strtolower(self::normalizarInstrumento($instrumento))])
            ->value('id_instrumento');

        return $id === null ? null : (int) $id;
    }

    public function actualizarContrasena(int $idUsuario, string $contrasena): void
    {
        Usuario::query()
            ->whereKey($idUsuario)
            ->update([
                'contrasena' => Hash::make($contrasena),
            ]);
    }
}
