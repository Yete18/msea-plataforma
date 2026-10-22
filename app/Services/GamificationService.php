<?php

namespace App\Services;

use App\Models\GamificacionPerfil;
use App\Models\Notificacion;
use Illuminate\Support\Facades\DB;

/**
 * Fuente unica de XP.
 *
 * Regla: todo XP pasa por otorgarXp(), que escribe una fila en xp_movimientos
 * con una clave unica (p. ej. "tarea:15", "practica:88", "teoria:4:12").
 * Si la clave ya existe no se otorga nada: las recompensas son idempotentes
 * aunque lleguen dos peticiones a la vez. gamificacion_perfiles.xp_total es
 * un resumen que se recalcula desde el libro dentro de la misma transaccion,
 * y la vista ranking_estudiantes lee ese mismo resumen.
 */
class GamificationService
{
    /** Codigo de logro => condicion minima. */
    private const LOGROS_POR_XP = ['500_xp' => 500, 'nivel_4' => 1500];

    private const LOGROS_POR_RACHA = ['racha_3' => 3, 'racha_7' => 7];

    /**
     * Otorga XP una sola vez por clave. Devuelve el XP realmente otorgado
     * (0 si la clave ya estaba registrada o el XP no es positivo).
     */
    public function otorgarXp(int $idEstudiante, int $xp, string $fuente, string $clave, string $descripcion = ''): int
    {
        if ($xp <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($idEstudiante, $xp, $fuente, $clave, $descripcion) {
            $insertado = DB::table('xp_movimientos')->insertOrIgnore([
                'id_estudiante' => $idEstudiante,
                'xp' => $xp,
                'fuente' => $fuente,
                'clave' => $clave,
                'descripcion' => mb_substr($descripcion, 0, 255),
                'created_at' => now(),
            ]);

            if ($insertado !== 1) {
                return 0;
            }

            $perfil = $this->sincronizarPerfil($idEstudiante);

            if ($fuente !== 'logro') {
                $this->notificar($idEstudiante, 'XP ganado', trim($descripcion).' (+'.$xp.' XP)', 'xp');
            }

            $this->evaluarLogros($idEstudiante, (int) $perfil->xp_total, (int) $perfil->racha_actual);

            return $xp;
        });
    }

    /**
     * Marca actividad del dia para la racha (practica, leccion o teoria).
     */
    public function registrarActividadDiaria(int $idEstudiante): void
    {
        DB::transaction(function () use ($idEstudiante) {
            $perfil = $this->perfilBloqueado($idEstudiante);

            $hoy = now()->toDateString();
            $ultima = $perfil->ultima_practica?->toDateString();

            // La racha cuenta dias con actividad. Se permite un dia de descanso
            // entre dos dias de practica sin perderla (UX-06): no suma, pero
            // tampoco reinicia.
            $racha = match (true) {
                $ultima === $hoy => max(1, (int) $perfil->racha_actual),
                $ultima !== null && $this->dentroDelMargen($ultima) => (int) $perfil->racha_actual + 1,
                default => 1,
            };

            $perfil->racha_actual = $racha;
            $perfil->mejor_racha = max((int) $perfil->mejor_racha, $racha);
            $perfil->ultima_practica = $hoy;
            $perfil->save();

            $this->evaluarLogros($idEstudiante, (int) $perfil->xp_total, $racha);
        });
    }

    public function xpDeFuenteHoy(int $idEstudiante, string $fuente): int
    {
        return (int) DB::table('xp_movimientos')
            ->where('id_estudiante', $idEstudiante)
            ->where('fuente', $fuente)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('xp');
    }

    public function levelForXp(int $xp): int
    {
        $tramo = max(1, (int) config('msea.xp.nivel_cada', 500));

        return max(1, intdiv(max(0, $xp), $tramo) + 1);
    }

    /**
     * Resumen de gamificacion que deben mostrar TODAS las pantallas.
     */
    public function resumen(int $idEstudiante): array
    {
        $perfil = DB::table('gamificacion_perfiles')->where('id_estudiante', $idEstudiante)->first();
        $xp = (int) ($perfil->xp_total ?? 0);
        $tramo = max(1, (int) config('msea.xp.nivel_cada', 500));
        $posicion = $this->posicionEnGrupo($idEstudiante);

        return [
            'xp_total' => $xp,
            'nivel' => $this->levelForXp($xp),
            'xp_en_nivel' => $xp % $tramo,
            'xp_por_nivel' => $tramo,
            'racha_actual' => $this->rachaVigente($perfil),
            'practico_hoy' => $perfil && $perfil->ultima_practica && substr((string) $perfil->ultima_practica, 0, 10) === now()->toDateString(),
            'mejor_racha' => (int) ($perfil->mejor_racha ?? 0),
            'posicion' => $posicion ? (int) $posicion : null,
            'logros' => (int) DB::table('estudiante_logro')->where('id_estudiante', $idEstudiante)->count(),
        ];
    }

    /**
     * Ranking del grupo del estudiante (decision de la fundacion, 2026-10):
     * - Se compara solo con su elenco. Si no tiene elenco, con los alumnos de su
     *   mismo profesor que tampoco tienen elenco. Si no tiene ninguno, solo el.
     * - De los demas se muestra solo el nombre de pila: nunca apellido,
     *   instrumento ni otros datos de menores.
     * - Posicion con empates (RANK): mismo XP, misma posicion.
     *
     * @return array<int, array{id_estudiante:int, nombre:string, puntos:int, posicion:int}>
     */
    public function rankingDelGrupo(int $idEstudiante): array
    {
        $estudiante = DB::table('estudiantes')->where('id_estudiante', $idEstudiante)->first(['id_elenco', 'id_profesor']);
        if (! $estudiante) {
            return [];
        }

        $consulta = DB::table('estudiantes as e')
            ->join('usuarios as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->leftJoin('gamificacion_perfiles as g', 'g.id_estudiante', '=', 'e.id_estudiante')
            ->where('u.activo', true);

        if ($estudiante->id_elenco) {
            $consulta->where('e.id_elenco', $estudiante->id_elenco);
        } elseif ($estudiante->id_profesor) {
            $consulta->whereNull('e.id_elenco')->where('e.id_profesor', $estudiante->id_profesor);
        } else {
            $consulta->where('e.id_estudiante', $idEstudiante);
        }

        $filas = $consulta
            ->orderByRaw('COALESCE(g.xp_total, 0) DESC')
            ->orderBy('u.nombres')
            ->get(['e.id_estudiante', 'u.nombres', DB::raw('COALESCE(g.xp_total, 0) as xp')]);

        $posicion = 0;
        $anterior = null;
        $resultado = [];
        foreach ($filas as $i => $fila) {
            if ($anterior === null || (int) $fila->xp !== $anterior) {
                $posicion = $i + 1;
                $anterior = (int) $fila->xp;
            }
            $resultado[] = [
                'id_estudiante' => (int) $fila->id_estudiante,
                'nombre' => self::nombreDePila((string) $fila->nombres),
                'puntos' => (int) $fila->xp,
                'posicion' => $posicion,
            ];
        }

        return $resultado;
    }

    public function posicionEnGrupo(int $idEstudiante): ?int
    {
        foreach ($this->rankingDelGrupo($idEstudiante) as $fila) {
            if ($fila['id_estudiante'] === $idEstudiante) {
                return $fila['posicion'];
            }
        }

        return null;
    }

    /** Primer nombre: "Carlos Andres" -> "Carlos". */
    public static function nombreDePila(string $nombres): string
    {
        $limpio = trim(preg_replace('/\s+/u', ' ', $nombres) ?? '');

        return $limpio === '' ? 'Estudiante' : explode(' ', $limpio)[0];
    }

    /**
     * Una racha sigue viva si la ultima actividad fue hoy o dentro del margen
     * de descanso (por defecto ayer o anteayer); si no, se muestra 0.
     */
    public function rachaVigente(?object $perfil): int
    {
        if (! $perfil || ! $perfil->ultima_practica) {
            return 0;
        }

        $ultima = substr((string) $perfil->ultima_practica, 0, 10);

        return $ultima === now()->toDateString() || $this->dentroDelMargen($ultima)
            ? (int) $perfil->racha_actual
            : 0;
    }

    /** Dias de descanso permitidos sin perder la racha (config msea.racha.dias_descanso). */
    public static function diasDescanso(): int
    {
        return max(0, (int) config('msea.racha.dias_descanso', 1));
    }

    /** La fecha (Y-m-d, anterior a hoy) esta a 1 + dias de descanso dias o menos. */
    private function dentroDelMargen(string $fecha): bool
    {
        $limite = now()->subDays(1 + self::diasDescanso())->toDateString();

        return $fecha >= $limite && $fecha < now()->toDateString();
    }

    private function sincronizarPerfil(int $idEstudiante): GamificacionPerfil
    {
        $perfil = $this->perfilBloqueado($idEstudiante);
        $total = (int) DB::table('xp_movimientos')->where('id_estudiante', $idEstudiante)->sum('xp');

        $perfil->xp_total = max(0, $total);
        $perfil->nivel = $this->levelForXp($perfil->xp_total);
        $perfil->save();

        return $perfil;
    }

    private function perfilBloqueado(int $idEstudiante): GamificacionPerfil
    {
        GamificacionPerfil::query()->firstOrCreate(
            ['id_estudiante' => $idEstudiante],
            ['xp_total' => 0, 'nivel' => 1, 'racha_actual' => 0, 'mejor_racha' => 0]
        );

        return GamificacionPerfil::query()
            ->where('id_estudiante', $idEstudiante)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Desbloquea logros una sola vez (indice unico en estudiante_logro) y
     * otorga su bono tambien una sola vez (clave "logro:{id}:{estudiante}").
     */
    private function evaluarLogros(int $idEstudiante, int $xpTotal, int $racha): void
    {
        $codigos = ['primer_paso'];
        foreach (self::LOGROS_POR_XP as $codigo => $minimo) {
            if ($xpTotal >= $minimo) {
                $codigos[] = $codigo;
            }
        }
        foreach (self::LOGROS_POR_RACHA as $codigo => $minimo) {
            if ($racha >= $minimo) {
                $codigos[] = $codigo;
            }
        }

        $logros = DB::table('logros')->whereIn('codigo', $codigos)->where('activo', true)->get();

        foreach ($logros as $logro) {
            $nuevo = DB::table('estudiante_logro')->insertOrIgnore([
                'id_estudiante' => $idEstudiante,
                'id_logro' => $logro->id_logro,
                'desbloqueado_at' => now(),
            ]);

            if ($nuevo !== 1) {
                continue;
            }

            $this->notificar($idEstudiante, 'Logro desbloqueado', $logro->nombre, 'logro');

            if ((int) $logro->xp_bonus > 0) {
                $this->otorgarXp(
                    $idEstudiante,
                    (int) $logro->xp_bonus,
                    'logro',
                    'logro:'.$logro->id_logro.':'.$idEstudiante,
                    'Bono por logro: '.$logro->nombre
                );
            }
        }
    }

    private function notificar(int $idEstudiante, string $titulo, string $mensaje, string $tipo): void
    {
        $idUsuario = DB::table('estudiantes')->where('id_estudiante', $idEstudiante)->value('id_usuario');

        if (! $idUsuario) {
            return;
        }

        Notificacion::query()->create([
            'id_usuario' => $idUsuario,
            'titulo' => $titulo,
            'mensaje' => mb_substr($mensaje, 0, 1000),
            'tipo' => $tipo,
        ]);
    }
}
