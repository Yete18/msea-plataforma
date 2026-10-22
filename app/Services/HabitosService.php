<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Habitos de practica: meta diaria, minutos por dia, semana actual y titulos de nivel.
 * Todo se calcula con datos reales (practicas finalizadas medidas por el servidor).
 */
class HabitosService
{
    /** Metas diarias que puede elegir el estudiante (minutos). */
    public const METAS = [10, 15, 20, 30, 45];

    public const META_POR_DEFECTO = 15;

    private const TITULOS = [
        1 => 'Semilla musical',
        2 => 'Primeras notas',
        3 => 'Aprendiz del arco',
        4 => 'Explorador de escalas',
        5 => 'Joven músico',
        6 => 'Intérprete',
        7 => 'Músico de cámara',
        8 => 'Solista',
        9 => 'Concertino',
    ];

    public function tituloNivel(int $nivel): string
    {
        return self::TITULOS[$nivel] ?? 'Maestro de la orquesta';
    }

    /** @return array<string, float> fecha (Y-m-d) => minutos practicados */
    public function minutosPorDia(int $idEstudiante, Carbon $desde, Carbon $hasta): array
    {
        return DB::table('practicas_autonomas')
            ->where('id_estudiante', $idEstudiante)
            ->where('estado', 'finalizada')
            ->whereBetween(DB::raw('coalesce(finalizada_at, created_at)'), [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->get([DB::raw('coalesce(finalizada_at, created_at) as fecha'), 'duracion_segundos'])
            ->groupBy(fn ($p) => Carbon::parse($p->fecha)->toDateString())
            ->map(fn ($dia) => round($dia->sum('duracion_segundos') / 60, 1))
            ->all();
    }

    public function minutosHoy(int $idEstudiante): float
    {
        return (float) ($this->minutosPorDia($idEstudiante, now(), now())[now()->toDateString()] ?? 0);
    }

    /**
     * Semana actual de lunes a domingo.
     *
     * @return array<int, array{fecha:string, dia:string, minutos:float, hoy:bool, futuro:bool, cumplida:bool}>
     */
    public function semanaActual(int $idEstudiante, ?int $meta = null): array
    {
        $lunes = now()->startOfWeek(Carbon::MONDAY);
        $minutos = $this->minutosPorDia($idEstudiante, $lunes, $lunes->copy()->addDays(6));
        $nombres = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
        $meta = $meta ?: self::META_POR_DEFECTO;

        return collect(range(0, 6))->map(function ($i) use ($lunes, $minutos, $nombres, $meta) {
            $fecha = $lunes->copy()->addDays($i);
            $min = (float) ($minutos[$fecha->toDateString()] ?? 0);

            return [
                'fecha' => $fecha->toDateString(),
                'dia' => $nombres[$i],
                'minutos' => $min,
                'hoy' => $fecha->isToday(),
                'futuro' => $fecha->isFuture() && ! $fecha->isToday(),
                'cumplida' => $min >= $meta,
            ];
        })->all();
    }

    /** Foto del estado de gamificacion para detectar subidas de nivel y logros nuevos. */
    public function instantanea(int $idEstudiante): array
    {
        return [
            'nivel' => (int) DB::table('gamificacion_perfiles')->where('id_estudiante', $idEstudiante)->value('nivel') ?: 1,
            'logros' => DB::table('estudiante_logro')->where('id_estudiante', $idEstudiante)->pluck('id_logro')->map(fn ($id) => (int) $id)->all(),
        ];
    }

    /** Diferencias entre dos instantaneas: nivel nuevo y nombres de logros nuevos. */
    public function novedades(array $antes, array $despues): array
    {
        $nuevos = array_values(array_diff($despues['logros'], $antes['logros']));

        return [
            'subio_nivel' => $despues['nivel'] > $antes['nivel'],
            'nivel' => $despues['nivel'],
            'titulo_nivel' => $this->tituloNivel($despues['nivel']),
            'logros_nuevos' => $nuevos
                ? DB::table('logros')->whereIn('id_logro', $nuevos)->pluck('nombre')->all()
                : [],
        ];
    }

    public function guardarMeta(int $idEstudiante, int $minutos): void
    {
        DB::table('estudiantes')->where('id_estudiante', $idEstudiante)->update(['meta_diaria_minutos' => $minutos]);
    }
}
