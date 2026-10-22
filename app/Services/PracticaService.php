<?php

namespace App\Services;

use App\Models\PracticaAutonoma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Practica autonoma medida por el servidor.
 *
 * 1. iniciar(): crea una sesion "en_curso" con la hora del SERVIDOR.
 * 2. finalizar(): la duracion es (ahora - inicio) segun el servidor, con tope.
 *    El XP sale de config('msea.xp'), respeta un tope diario y se registra
 *    con la clave "practica:{id}" (finalizar dos veces no da XP doble).
 *
 * Las metricas que manda el navegador (nota detectada, precision de ritmo...)
 * se guardan como NO verificadas y nunca cambian el XP.
 */
class PracticaService
{
    public function __construct(private readonly GamificationService $gamificacion) {}

    public function iniciar(object $estudiante, string $tipo, ?int $idEjercicio): PracticaAutonoma
    {
        $this->validarTipo($tipo);

        if ($idEjercicio !== null && ! DB::table('ejercicios')->where('id_ejercicio', $idEjercicio)->exists()) {
            throw ValidationException::withMessages(['id_ejercicio' => 'El ejercicio no existe.']);
        }

        // Sesiones abandonadas: se cierran sin XP.
        PracticaAutonoma::query()
            ->where('id_estudiante', $estudiante->id_estudiante)
            ->where('estado', 'en_curso')
            ->where('iniciada_at', '<', now()->subMinutes((int) config('msea.practica.sesion_caduca_minutos', 120)))
            ->update(['estado' => 'caducada', 'updated_at' => now()]);

        // BD-10: solo una practica en curso a la vez. La que seguia abierta se
        // cierra ahora (con su XP real), asi los minutos del dia no se solapan.
        $abiertas = PracticaAutonoma::query()
            ->where('id_estudiante', $estudiante->id_estudiante)
            ->where('estado', 'en_curso')
            ->pluck('id_practica_autonoma');
        foreach ($abiertas as $idAbierta) {
            $this->finalizar($estudiante, (int) $idAbierta);
        }

        return PracticaAutonoma::query()->create([
            'id_estudiante' => $estudiante->id_estudiante,
            'tipo' => $tipo,
            'id_ejercicio' => $idEjercicio,
            'estado' => 'en_curso',
            'iniciada_at' => now(),
            'duracion_segundos' => 0,
            'xp_ganado' => 0,
        ]);
    }

    /**
     * @return array{xp:int, duracion_segundos:int, tope_alcanzado:bool, motivo:?string, practica:PracticaAutonoma}
     */
    public function finalizar(object $estudiante, int $idPractica, array $metricas = []): array
    {
        return DB::transaction(function () use ($estudiante, $idPractica, $metricas) {
            $practica = PracticaAutonoma::query()
                ->whereKey($idPractica)
                ->where('id_estudiante', $estudiante->id_estudiante)
                ->lockForUpdate()
                ->first();

            if (! $practica) {
                throw new NotFoundHttpException('Practica no encontrada');
            }

            // Bloquea la fila del estudiante: dos finalizaciones simultaneas no
            // pueden leer el mismo XP del dia y pasarse juntas del tope diario.
            DB::table('estudiantes')->where('id_estudiante', $estudiante->id_estudiante)->lockForUpdate()->value('id_estudiante');

            if ($practica->estado !== 'en_curso') {
                return [
                    'xp' => 0,
                    'duracion_segundos' => (int) $practica->duracion_segundos,
                    'tope_alcanzado' => false,
                    'motivo' => 'Esta práctica ya fue registrada.',
                    'practica' => $practica,
                ];
            }

            $duracion = (int) min(
                max(0, $practica->iniciada_at->diffInSeconds(now(), true)),
                (int) config('msea.xp.duracion_maxima_segundos', 3600)
            );

            [$xp, $tope, $motivo] = $this->calcularXp($estudiante, $practica->tipo, $duracion);

            $practica->fill([
                'estado' => 'finalizada',
                'finalizada_at' => now(),
                'duracion_segundos' => $duracion,
                'metricas' => $this->limpiarMetricas($metricas),
                'metricas_verificadas' => false,
                'notas' => isset($metricas['nota_detectada']) ? mb_substr((string) $metricas['nota_detectada'], 0, 10) : null,
            ]);

            $otorgado = $xp > 0
                ? $this->gamificacion->otorgarXp(
                    (int) $estudiante->id_estudiante,
                    $xp,
                    'practica',
                    'practica:'.$practica->id_practica_autonoma,
                    'Practica de '.$practica->tipo
                )
                : 0;

            $practica->xp_ganado = $otorgado;
            $practica->save();

            $this->guardarDetalle($estudiante, $practica, $metricas, $otorgado);

            if ($duracion >= (int) config('msea.xp.duracion_minima_segundos', 30)) {
                $this->gamificacion->registrarActividadDiaria((int) $estudiante->id_estudiante);
            }

            return [
                'xp' => $otorgado,
                'duracion_segundos' => $duracion,
                'tope_alcanzado' => $tope,
                'motivo' => $motivo,
                'practica' => $practica,
            ];
        });
    }

    /** @return array{0:int,1:bool,2:?string} */
    private function calcularXp(object $estudiante, string $tipo, int $duracion): array
    {
        $minimo = (int) config('msea.xp.duracion_minima_segundos', 30);

        if ($duracion < $minimo) {
            return [0, false, "La practica debe durar al menos {$minimo} segundos para sumar XP."];
        }

        $base = (int) (config('msea.xp.practica.'.$tipo) ?? 10);
        $bono = min(
            (int) config('msea.xp.bono_maximo_por_practica', 10),
            intdiv($duracion, 60) * (int) config('msea.xp.bono_por_minuto', 1)
        );

        $tope = (int) config('msea.xp.tope_diario_practica', 150);
        $disponible = max(0, $tope - $this->gamificacion->xpDeFuenteHoy((int) $estudiante->id_estudiante, 'practica'));
        $xp = min($base + $bono, $disponible);

        if ($disponible === 0) {
            return [0, true, 'Alcanzaste el máximo de XP por práctica de hoy. Tu práctica igual quedó registrada.'];
        }

        return [$xp, $xp < $base + $bono, null];
    }

    private function guardarDetalle(object $estudiante, PracticaAutonoma $practica, array $metricas, int $xp): void
    {
        if ($practica->tipo === 'afinacion' && (isset($metricas['frecuencia']) || isset($metricas['nota_detectada']))) {
            DB::table('sesiones_afinacion')->insert([
                'id_estudiante' => $estudiante->id_estudiante,
                'instrumento' => isset($metricas['instrumento']) ? mb_substr((string) $metricas['instrumento'], 0, 50) : null,
                'nota_objetivo' => $metricas['nota_objetivo'] ?? null,
                'nota_detectada' => $metricas['nota_detectada'] ?? null,
                'frecuencia' => $metricas['frecuencia'] ?? null,
                'desviacion_cents' => $metricas['desviacion_cents'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($practica->tipo === 'ritmo' && isset($metricas['bpm'])) {
            DB::table('sesiones_ritmo')->insert([
                'id_estudiante' => $estudiante->id_estudiante,
                'bpm' => (int) $metricas['bpm'],
                'patron' => $metricas['patron'] ?? '4/4',
                // Precision medida en el navegador (no verificada); null si no se midio.
                'precision' => $metricas['precision'] ?? null,
                'xp_ganado' => $xp,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function limpiarMetricas(array $metricas): ?array
    {
        $permitidas = ['nota_objetivo', 'nota_detectada', 'frecuencia', 'desviacion_cents', 'bpm', 'patron', 'precision', 'golpes', 'instrumento', 'fuente'];
        $limpias = array_intersect_key($metricas, array_flip($permitidas));

        return $limpias ?: null;
    }

    private function validarTipo(string $tipo): void
    {
        if (! in_array($tipo, (array) config('msea.practica.tipos'), true)) {
            throw ValidationException::withMessages(['tipo' => 'Tipo de práctica no válido.']);
        }
    }
}
