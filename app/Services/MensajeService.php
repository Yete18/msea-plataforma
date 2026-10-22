<?php

namespace App\Services;

use App\Models\Mensaje;
use App\Models\Notificacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mensajes internos. Solo se permiten conversaciones con relacion academica:
 *  - padre      <-> profesor de alguno de sus hijos vinculados
 *  - estudiante <-> su profesor asignado
 *  - director puede escribir a cualquiera; cualquiera puede responder al director.
 */
class MensajeService
{
    public function contactos(int $idUsuario, string $rol): array
    {
        $query = match ($rol) {
            'padre' => DB::table('estudiante_padre as ep')
                ->join('padres as pa', 'pa.id_padre', '=', 'ep.id_padre')
                ->join('estudiantes as e', 'e.id_estudiante', '=', 'ep.id_estudiante')
                ->join('profesores as p', 'p.id_profesor', '=', 'e.id_profesor')
                ->join('usuarios as u', 'u.id_usuario', '=', 'p.id_usuario')
                ->where('pa.id_usuario', $idUsuario),
            'estudiante' => DB::table('estudiantes as e')
                ->join('profesores as p', 'p.id_profesor', '=', 'e.id_profesor')
                ->join('usuarios as u', 'u.id_usuario', '=', 'p.id_usuario')
                ->where('e.id_usuario', $idUsuario),
            'profesor' => DB::table('estudiantes as e')
                ->join('profesores as p', 'p.id_profesor', '=', 'e.id_profesor')
                ->leftJoin('estudiante_padre as ep', 'ep.id_estudiante', '=', 'e.id_estudiante')
                ->leftJoin('padres as pa', 'pa.id_padre', '=', 'ep.id_padre')
                ->join('usuarios as u', function ($join) {
                    $join->on('u.id_usuario', '=', 'pa.id_usuario')->orOn('u.id_usuario', '=', 'e.id_usuario');
                })
                ->where('p.id_usuario', $idUsuario),
            'director' => DB::table('usuarios as u')->where('u.id_usuario', '<>', $idUsuario),
            default => null,
        };

        if (! $query) {
            return [];
        }

        return $query->where('u.activo', true)
            ->join('roles as r', 'r.id_rol', '=', 'u.id_rol')
            ->distinct()
            ->orderBy('u.nombres')
            ->limit(300)
            ->get(['u.id_usuario', 'u.nombres', 'u.apellido_paterno', 'r.nombre as rol'])
            ->map(fn ($u) => ['id' => (int) $u->id_usuario, 'nombre' => trim($u->nombres.' '.$u->apellido_paterno), 'rol' => $u->rol])
            ->values()
            ->all();
    }

    public function puedeEscribir(int $idUsuario, string $rol, int $idDestinatario): bool
    {
        if ($idDestinatario === $idUsuario) {
            return false;
        }

        if ($rol === 'director') {
            return DB::table('usuarios')->where('id_usuario', $idDestinatario)->exists();
        }

        // Responder a quien ya te escribio (incluido el director).
        if (DB::table('mensajes')->where('id_remitente', $idDestinatario)->where('id_destinatario', $idUsuario)->exists()) {
            return true;
        }

        return collect($this->contactos($idUsuario, $rol))->contains('id', $idDestinatario);
    }

    public function enviar(int $idUsuario, string $rol, int $idDestinatario, ?string $asunto, string $contenido): Mensaje
    {
        if (! $this->puedeEscribir($idUsuario, $rol, $idDestinatario)) {
            throw ValidationException::withMessages(['id_destinatario' => 'No puedes enviar mensajes a este usuario.']);
        }

        return DB::transaction(function () use ($idUsuario, $idDestinatario, $asunto, $contenido) {
            $mensaje = Mensaje::query()->create([
                'id_remitente' => $idUsuario,
                'id_destinatario' => $idDestinatario,
                'asunto' => $asunto ? mb_substr($asunto, 0, 150) : null,
                'contenido' => $contenido,
            ]);

            $remitente = DB::table('usuarios')->where('id_usuario', $idUsuario)->first(['nombres', 'apellido_paterno']);
            Notificacion::query()->create([
                'id_usuario' => $idDestinatario,
                'titulo' => 'Nuevo mensaje',
                'mensaje' => 'De '.trim($remitente->nombres.' '.$remitente->apellido_paterno).($asunto ? ': '.$asunto : ''),
                'tipo' => 'mensaje',
            ]);

            return $mensaje;
        });
    }

    public function bandeja(int $idUsuario, int $limite = 30): array
    {
        return DB::table('mensajes as m')
            ->join('usuarios as r', 'r.id_usuario', '=', 'm.id_remitente')
            ->join('usuarios as d', 'd.id_usuario', '=', 'm.id_destinatario')
            ->where(fn ($q) => $q->where('m.id_remitente', $idUsuario)->orWhere('m.id_destinatario', $idUsuario))
            ->orderByDesc('m.created_at')
            ->orderByDesc('m.id_mensaje')
            ->limit($limite)
            ->get([
                'm.id_mensaje', 'm.id_remitente', 'm.id_destinatario', 'm.asunto', 'm.contenido', 'm.leido_at', 'm.created_at',
                'r.nombres as r_nombres', 'r.apellido_paterno as r_apellido', 'd.nombres as d_nombres', 'd.apellido_paterno as d_apellido',
            ])
            ->map(fn ($m) => [
                'id' => (int) $m->id_mensaje,
                'enviado' => (int) $m->id_remitente === $idUsuario,
                'con_id' => (int) ((int) $m->id_remitente === $idUsuario ? $m->id_destinatario : $m->id_remitente),
                'con' => (int) $m->id_remitente === $idUsuario
                    ? trim($m->d_nombres.' '.$m->d_apellido)
                    : trim($m->r_nombres.' '.$m->r_apellido),
                'asunto' => $m->asunto,
                'contenido' => $m->contenido,
                'leido' => (bool) $m->leido_at,
                'fecha' => $m->created_at,
            ])
            ->all();
    }

    public function marcarLeidos(int $idUsuario): int
    {
        return DB::table('mensajes')->where('id_destinatario', $idUsuario)->whereNull('leido_at')->update(['leido_at' => now()]);
    }
}
