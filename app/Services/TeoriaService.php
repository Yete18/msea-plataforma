<?php

namespace App\Services;

use App\Models\TeoriaIntento;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Preguntas de teoria evaluadas en el servidor. La respuesta correcta nunca
 * se envia al navegador; el XP de cada pregunta se otorga una sola vez
 * (clave "teoria:{id_teoria}:{id_estudiante}").
 */
class TeoriaService
{
    public function __construct(private readonly GamificationService $gamificacion) {}

    /** Preguntas sin la respuesta correcta. */
    public function preguntasPara(object $estudiante, ?int $idLeccion = null, int $limite = 10): array
    {
        $acertadas = DB::table('teoria_intentos')
            ->where('id_estudiante', $estudiante->id_estudiante)
            ->where('correcta', true)
            ->pluck('id_teoria')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->consultaDisponible($estudiante)
            ->when($idLeccion, fn ($q) => $q->where('tc.id_leccion', $idLeccion))
            ->orderBy('tc.id_teoria')
            ->limit($limite)
            ->get(['tc.id_teoria', 'tc.titulo', 'tc.pregunta', 'tc.opciones', 'tc.xp'])
            ->map(fn ($t) => [
                'id' => (int) $t->id_teoria,
                'titulo' => $t->titulo,
                'pregunta' => $t->pregunta,
                'opciones' => array_values(array_map('strval', (array) json_decode($t->opciones ?: '[]', true))),
                'xp' => (int) $t->xp,
                'resuelta' => in_array((int) $t->id_teoria, $acertadas, true),
            ])
            ->all();
    }

    /**
     * @return array{correcta:bool, xp:int, ya_resuelta:bool, respuesta_correcta:?string}
     */
    public function responder(object $estudiante, int $idTeoria, string $respuesta): array
    {
        $pregunta = $this->consultaDisponible($estudiante)->where('tc.id_teoria', $idTeoria)->first(['tc.*']);

        if (! $pregunta) {
            throw ValidationException::withMessages(['pregunta' => 'La pregunta no existe o no está disponible para ti.']);
        }

        $opciones = array_map('strval', (array) json_decode($pregunta->opciones ?: '[]', true));
        if ($opciones && ! in_array($respuesta, $opciones, true)) {
            throw ValidationException::withMessages(['respuesta' => 'Elige una de las opciones de la pregunta.']);
        }

        $correcta = mb_strtolower(trim($respuesta)) === mb_strtolower(trim((string) $pregunta->respuesta_correcta));

        // SEC-05: tras un error se muestra la respuesta correcta, así que el XP
        // completo es solo para quien acierta al primer intento.
        $primerIntento = ! TeoriaIntento::query()
            ->where('id_teoria', $idTeoria)
            ->where('id_estudiante', $estudiante->id_estudiante)
            ->exists();

        TeoriaIntento::query()->create([
            'id_teoria' => $idTeoria,
            'id_estudiante' => $estudiante->id_estudiante,
            'respuesta' => mb_substr($respuesta, 0, 150),
            'correcta' => $correcta,
            'created_at' => now(),
        ]);

        $xp = 0;
        if ($correcta) {
            $xpPregunta = (int) $pregunta->xp;
            $xp = $this->gamificacion->otorgarXp(
                (int) $estudiante->id_estudiante,
                $primerIntento ? $xpPregunta : self::xpTrasError($xpPregunta),
                'teoria',
                'teoria:'.$idTeoria.':'.$estudiante->id_estudiante,
                'Teoria: '.$pregunta->titulo
            );
            $this->gamificacion->registrarActividadDiaria((int) $estudiante->id_estudiante);
        }

        return [
            'correcta' => $correcta,
            'xp' => $xp,
            'primer_intento' => $primerIntento,
            'ya_resuelta' => $correcta && $xp === 0,
            // Solo se revela despues de responder.
            'respuesta_correcta' => $correcta ? null : (string) $pregunta->respuesta_correcta,
        ];
    }

    /** XP al acertar después de haber fallado (y visto la respuesta): un cuarto, mínimo 1. */
    public static function xpTrasError(int $xp): int
    {
        return $xp > 0 ? max(1, intdiv($xp, 4)) : 0;
    }

    /**
     * Preguntas activas: sueltas (sin leccion) o de lecciones publicadas de
     * cursos activos en los que el estudiante esta inscrito.
     */
    private function consultaDisponible(object $estudiante)
    {
        return DB::table('teoria_contenidos as tc')
            ->leftJoin('lecciones as l', 'l.id_leccion', '=', 'tc.id_leccion')
            ->leftJoin('modulos as m', 'm.id_modulo', '=', 'l.id_modulo')
            ->where('tc.activo', true)
            ->where(function ($q) use ($estudiante) {
                $q->whereNull('tc.id_leccion')
                    ->orWhere(function ($q2) use ($estudiante) {
                        $q2->where('l.publicado', true)
                            ->where('m.publicado', true)
                            ->whereExists(function ($q3) use ($estudiante) {
                                $q3->selectRaw('1')
                                    ->from('curso_estudiante as ce')
                                    ->join('cursos as c', 'c.id_curso', '=', 'ce.id_curso')
                                    ->whereColumn('ce.id_curso', 'm.id_curso')
                                    ->where('c.activo', true)
                                    ->where('ce.id_estudiante', $estudiante->id_estudiante);
                            });
                    });
            });
    }
}
