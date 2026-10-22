<?php

namespace App\Services;

use App\Models\Padre;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Acciones del director sobre cuentas. Nunca se borra un usuario con
 * historial: se desactiva (borrado logico) y se puede reactivar.
 */
class GestionUsuariosService
{
    public function __construct(
        private readonly MobileTokenService $tokens,
        private readonly AuditLogger $audit,
        private readonly PasswordResetService $recuperacion,
    ) {}

    public function editarUsuario(int $idActor, int $idUsuario, array $datos): void
    {
        $usuario = Usuario::query()->findOrFail($idUsuario);
        $correoNuevo = AuthService::normalizarCorreo($datos['correo']);
        $cambiaCorreo = $correoNuevo !== $usuario->correo;

        // SEC-11: el correo recibe los codigos de recuperacion. Un director no
        // puede cambiar el de OTRO director (seria tomar su cuenta); cada uno
        // cambia el suyo desde su perfil, confirmando su contrasena.
        if ($cambiaCorreo && $idActor !== $idUsuario && $this->esDirector($idUsuario)) {
            throw ValidationException::withMessages([
                'correo' => 'El correo de otro director solo lo cambia esa persona desde su perfil.',
            ]);
        }

        DB::transaction(function () use ($usuario, $datos, $correoNuevo, $idUsuario) {
            $usuario->update([
                'nombres' => $datos['nombres'],
                'apellido_paterno' => $datos['apellido_paterno'] ?? null,
                'apellido_materno' => $datos['apellido_materno'] ?? null,
                'correo' => $correoNuevo,
                'celular' => $datos['celular'] ?? null,
                'ci' => $datos['ci'] ?? $usuario->ci,
                // Solo la dirección corrige estos datos oficiales (UX-09).
                'direccion' => array_key_exists('direccion', $datos) ? $datos['direccion'] : $usuario->direccion,
                'fecha_nacimiento' => array_key_exists('fecha_nacimiento', $datos) ? $datos['fecha_nacimiento'] : $usuario->fecha_nacimiento,
            ]);

            if (array_key_exists('instrumento', $datos) && $datos['instrumento']) {
                $idInstrumento = DB::table('instrumentos')->where('id_instrumento', (int) $datos['instrumento'])->value('id_instrumento');
                if ($idInstrumento) {
                    DB::table('usuario_instrumento')->where('id_usuario', $idUsuario)->delete();
                    DB::table('usuario_instrumento')->insert(['id_usuario' => $idUsuario, 'id_instrumento' => $idInstrumento]);
                }
            }

            if (array_key_exists('id_seccion', $datos) && $datos['id_seccion']) {
                DB::table('estudiantes')->where('id_usuario', $idUsuario)->update(['id_seccion' => (int) $datos['id_seccion']]);
            }
        });

        if ($cambiaCorreo) {
            $this->tokens->revocarTodos($idUsuario);
        }

        $this->audit->registrar('usuario_editado', $idActor, ['usuario' => $idUsuario, 'cambio_correo' => $cambiaCorreo]);
    }

    public function cambiarEstado(int $idActor, int $idUsuario, bool $activo): void
    {
        if ($idActor === $idUsuario) {
            throw ValidationException::withMessages(['usuario' => 'No puedes desactivar tu propia cuenta.']);
        }

        Usuario::query()->whereKey($idUsuario)->update([
            'activo' => $activo,
            'desactivado_at' => $activo ? null : now(),
        ]);

        if (! $activo) {
            // Las sesiones web caen solas en la siguiente peticion (middleware).
            $this->tokens->revocarTodos($idUsuario);
        }

        $this->audit->registrar($activo ? 'usuario_reactivado' : 'usuario_desactivado', $idActor, ['usuario' => $idUsuario]);
    }

    /** Envia un codigo de recuperacion al correo del usuario. El director nunca ve la contrasena. */
    public function enviarRecuperacion(int $idActor, int $idUsuario): void
    {
        $usuario = Usuario::query()->findOrFail($idUsuario);
        $this->recuperacion->enviarCodigoSiUsuarioExiste($usuario->correo);
        $this->audit->registrar('reinicio_contrasena_admin', $idActor, ['usuario' => $idUsuario]);
    }

    public function crearPadre(int $idActor, array $datos): Usuario
    {
        $rol = Rol::query()->firstOrCreate(['nombre' => 'padre']);

        $usuario = DB::transaction(function () use ($datos, $rol) {
            $usuario = Usuario::query()->create([
                'correo' => AuthService::normalizarCorreo($datos['correo']),
                'contrasena' => Hash::make($datos['contrasena']),
                'nombres' => $datos['nombres'],
                'apellido_paterno' => $datos['apellido_paterno'] ?? null,
                'apellido_materno' => $datos['apellido_materno'] ?? null,
                'celular' => $datos['celular'] ?? null,
                'ci' => $datos['ci'] ?? null,
                'id_rol' => $rol->id_rol,
            ]);

            $padre = Padre::query()->create([
                'id_usuario' => $usuario->id_usuario,
                'parentesco' => $datos['parentesco'] ?? 'Padre/Madre',
            ]);

            foreach ((array) ($datos['estudiantes'] ?? []) as $idEstudiante) {
                $this->vincularInterno($padre->id_padre, (int) $idEstudiante);
            }

            return $usuario;
        });

        $this->audit->registrar('padre_creado', $idActor, ['usuario' => $usuario->id_usuario]);

        return $usuario;
    }

    public function vincular(int $idActor, int $idPadre, int $idEstudiante): void
    {
        Padre::query()->findOrFail($idPadre);
        abort_unless(DB::table('estudiantes')->where('id_estudiante', $idEstudiante)->exists(), 404);

        $this->vincularInterno($idPadre, $idEstudiante);
        $this->audit->registrar('padre_vinculado', $idActor, ['padre' => $idPadre, 'estudiante' => $idEstudiante]);
    }

    public function desvincular(int $idActor, int $idPadre, int $idEstudiante): void
    {
        DB::table('estudiante_padre')->where('id_padre', $idPadre)->where('id_estudiante', $idEstudiante)->delete();
        $this->audit->registrar('padre_desvinculado', $idActor, ['padre' => $idPadre, 'estudiante' => $idEstudiante]);
    }

    public function padres(): array
    {
        $vinculos = DB::table('estudiante_padre as ep')
            ->join('estudiantes as e', 'e.id_estudiante', '=', 'ep.id_estudiante')
            ->join('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->get(['ep.id_padre', 'e.id_estudiante', 'u.nombres', 'u.apellido_paterno'])
            ->groupBy('id_padre');

        return DB::table('padres as p')
            ->join('usuarios as u', 'u.id_usuario', '=', 'p.id_usuario')
            ->orderBy('u.apellido_paterno')
            ->get(['p.id_padre', 'p.parentesco', 'u.id_usuario', 'u.nombres', 'u.apellido_paterno', 'u.apellido_materno', 'u.correo', 'u.celular', 'u.activo'])
            ->map(fn ($p) => [
                'id' => (int) $p->id_padre,
                'id_usuario' => (int) $p->id_usuario,
                'nombres' => $p->nombres,
                'apellido_paterno' => $p->apellido_paterno,
                'apellido_materno' => $p->apellido_materno,
                'nombre' => trim($p->nombres.' '.$p->apellido_paterno),
                'correo' => $p->correo,
                'celular' => $p->celular,
                'parentesco' => $p->parentesco,
                'activo' => (bool) $p->activo,
                'hijos' => ($vinculos[$p->id_padre] ?? collect())->map(fn ($h) => [
                    'id' => (int) $h->id_estudiante,
                    'nombre' => trim($h->nombres.' '.$h->apellido_paterno),
                ])->values()->all(),
            ])
            ->all();
    }

    /**
     * Comunicado institucional: crea una notificacion para todos los usuarios
     * activos de los roles elegidos (o de un elenco).
     */
    public function enviarComunicado(int $idActor, string $titulo, string $mensaje, array $roles, ?int $idElenco = null): int
    {
        $query = DB::table('usuarios as u')->join('roles as r', 'r.id_rol', '=', 'u.id_rol')->where('u.activo', true);

        if ($idElenco) {
            $query->join('estudiantes as e', 'e.id_usuario', '=', 'u.id_usuario')->where('e.id_elenco', $idElenco);
        } else {
            $query->whereIn('r.nombre', $roles);
        }

        $ids = $query->pluck('u.id_usuario')->unique();

        DB::transaction(function () use ($ids, $titulo, $mensaje) {
            foreach ($ids->chunk(200) as $grupo) {
                DB::table('notificaciones')->insert($grupo->map(fn ($id) => [
                    'id_usuario' => $id,
                    'titulo' => mb_substr($titulo, 0, 150),
                    'mensaje' => $mensaje,
                    'tipo' => 'comunicado',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->values()->all());
            }
        });

        $this->audit->registrar('comunicado_enviado', $idActor, ['destinatarios' => $ids->count(), 'roles' => $roles, 'elenco' => $idElenco]);

        return $ids->count();
    }

    private function vincularInterno(int $idPadre, int $idEstudiante): void
    {
        DB::table('estudiante_padre')->insertOrIgnore([
            'id_padre' => $idPadre,
            'id_estudiante' => $idEstudiante,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function esDirector(int $idUsuario): bool
    {
        return DB::table('usuarios as u')->join('roles as r', 'r.id_rol', '=', 'u.id_rol')
            ->where('u.id_usuario', $idUsuario)->where('r.nombre', 'director')->exists();
    }
}
