<?php

use App\Support\ReversionSegura;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las mismas 48 escalas del violín (12 mayores y 12 menores en sus tres formas), con el
 * mismo nombre, para viola, violonchelo y contrabajo. Así una tarea de elenco sirve a
 * cada estudiante en su instrumento.
 *
 * Octava de la tónica (sonido real):
 *  - Viola: desde Do3. En 1 octava, Re empieza en la cuerda Re (Re4); el resto en la octava 3.
 *  - Violonchelo: desde Do2. En 1 octava, Re empieza en la cuerda Re (Re3); el resto en la octava 2.
 *  - Contrabajo: desde Mi1 (Mi a Si en la octava 1; Do a Mi♭ en la octava 2).
 * Si 3 octavas superan el registro fijado para el instrumento, la escala llega hasta 2.
 */
return new class extends Migration
{
    private const SOLFEO = ['C' => 'Do', 'D' => 'Re', 'E' => 'Mi', 'F' => 'Fa', 'G' => 'Sol', 'A' => 'La', 'B' => 'Si'];

    private const CLASE = ['C' => 0, 'D' => 2, 'E' => 4, 'F' => 5, 'G' => 7, 'A' => 9, 'B' => 11];

    private const MAYORES = [
        ['C', 0, 'facil'], ['G', 0, 'facil'], ['D', 0, 'facil'], ['A', 0, 'facil'],
        ['E', 0, 'intermedio'], ['B', 0, 'intermedio'], ['F', 1, 'dificil'],
        ['F', 0, 'intermedio'], ['B', -1, 'intermedio'], ['E', -1, 'intermedio'],
        ['A', -1, 'dificil'], ['D', -1, 'dificil'],
    ];

    private const MENORES = [
        ['A', 0, 'facil'], ['E', 0, 'facil'], ['D', 0, 'facil'], ['B', 0, 'intermedio'],
        ['G', 0, 'intermedio'], ['C', 0, 'intermedio'], ['F', 1, 'intermedio'],
        ['F', 0, 'dificil'], ['C', 1, 'dificil'], ['B', -1, 'dificil'],
        ['G', 1, 'dificil'], ['E', -1, 'dificil'],
    ];

    private const MODOS_MENORES = ['menor_natural' => 'menor natural', 'menor_armonica' => 'menor armónica', 'menor_melodica' => 'menor melódica'];

    /** Nota más aguda (MIDI) aceptada para cada instrumento, igual que EscalaService::INSTRUMENTOS. */
    private const TECHO = ['viola' => 93, 'violonchelo' => 88, 'contrabajo' => 72];

    public function up(): void
    {
        foreach (array_keys(self::TECHO) as $instrumento) {
            $orden = 100;
            foreach (self::MAYORES as [$letra, $alter, $dificultad]) {
                $this->agregar($instrumento, $letra, $alter, 'mayor', 'mayor', $dificultad, $orden++);
            }
            $orden = 200;
            foreach (self::MENORES as [$letra, $alter, $dificultad]) {
                foreach (self::MODOS_MENORES as $modo => $texto) {
                    $nivel = $modo === 'menor_natural' ? $dificultad : ($dificultad === 'facil' ? 'intermedio' : 'dificil');
                    $this->agregar($instrumento, $letra, $alter, $modo, $texto, $nivel, $orden++);
                }
            }
        }
    }

    public function down(): void
    {
        ReversionSegura::verificar('2026_10_15_000001_msea_escalas_viola_chelo_contrabajo');

        foreach (array_keys(self::TECHO) as $instrumento) {
            $ids = DB::table('biblioteca_ejercicios')->where('categoria', 'escala')->where('instrumento', $instrumento)->pluck('id_biblioteca');
            foreach ($ids as $id) {
                if (! DB::table('tareas')->where('id_biblioteca', $id)->exists()) {
                    DB::table('biblioteca_ejercicios')->where('id_biblioteca', $id)->delete();
                }
            }
        }
    }

    /** Octava de la tónica: [1 octava, 2 y 3 octavas]. */
    private function octavas(string $instrumento, string $letra, int $alter): array
    {
        $clase = self::CLASE[$letra] + $alter;
        $esRe = $letra === 'D' && $alter === 0;

        return match ($instrumento) {
            'viola' => [$esRe ? 4 : 3, 3],
            'violonchelo' => [$esRe ? 3 : 2, 2],
            'contrabajo' => [$clase >= 4 ? 1 : 2, $clase >= 4 ? 1 : 2],
        };
    }

    private function agregar(string $instrumento, string $letra, int $alter, string $modo, string $textoModo, string $dificultad, int $orden): void
    {
        $nombre = self::SOLFEO[$letra].($alter > 0 ? '♯' : ($alter < 0 ? '♭' : '')).' '.$textoModo;
        if (DB::table('biblioteca_ejercicios')->where('categoria', 'escala')->where('instrumento', $instrumento)->where('nombre', $nombre)->exists()) {
            return;
        }

        [$una, $grave] = $this->octavas($instrumento, $letra, $alter);
        $tonicaGrave = 12 * ($grave + 1) + self::CLASE[$letra] + $alter;
        $inicio = ['1' => $una, '2' => $grave];
        if ($tonicaGrave + 36 <= self::TECHO[$instrumento]) {
            $inicio['3'] = $grave;
        }

        DB::table('biblioteca_ejercicios')->insert([
            'categoria' => 'escala',
            'instrumento' => $instrumento,
            'nombre' => $nombre,
            'dificultad' => $dificultad,
            'descripcion' => null,
            'datos' => json_encode([
                'tonica' => $letra,
                'alteracion' => $alter,
                'modo' => $modo,
                'inicio' => $inicio,
                'octavas_max' => count($inicio),
                'instrumento' => $instrumento,
            ], JSON_UNESCAPED_UNICODE),
            'activo' => true,
            'orden' => $orden,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
