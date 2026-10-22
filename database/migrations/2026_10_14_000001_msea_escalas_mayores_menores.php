<?php

use App\Support\ReversionSegura;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carga en la biblioteca técnica las 12 escalas mayores y las 12 menores
 * (natural, armónica y melódica) para violín, de 1 a 3 octavas.
 *
 * Octava de la tónica:
 *  - 1 octava: Sol, La♭, Si♭ y Si empiezan en la octava 3 (cuerda Sol); La en La4
 *    (cuerda La al aire, como la escala ya aprobada); el resto en la octava 4.
 *  - 2 y 3 octavas: la tónica más grave posible desde Sol3.
 * La cuerda y el dedo se calculan solos cuando la escala cabe en primera posición.
 *
 * Solo agrega datos de referencia; no toca las escalas que ya existan con el mismo nombre.
 */
return new class extends Migration
{
    private const SOLFEO = ['C' => 'Do', 'D' => 'Re', 'E' => 'Mi', 'F' => 'Fa', 'G' => 'Sol', 'A' => 'La', 'B' => 'Si'];

    private const CLASE = ['C' => 0, 'D' => 2, 'E' => 4, 'F' => 5, 'G' => 7, 'A' => 9, 'B' => 11];

    /** [letra, alteración, dificultad] en orden del círculo de quintas. */
    private const MAYORES = [
        ['C', 0, 'facil'], ['G', 0, 'facil'], ['D', 0, 'facil'], ['A', 0, 'facil'],
        ['E', 0, 'intermedio'], ['B', 0, 'intermedio'], ['F', 1, 'dificil'],
        ['F', 0, 'intermedio'], ['B', -1, 'intermedio'], ['E', -1, 'intermedio'],
        ['A', -1, 'dificil'], ['D', -1, 'dificil'],
    ];

    /** Dificultad de la menor natural; la armónica y la melódica suben un nivel. */
    private const MENORES = [
        ['A', 0, 'facil'], ['E', 0, 'facil'], ['D', 0, 'facil'], ['B', 0, 'intermedio'],
        ['G', 0, 'intermedio'], ['C', 0, 'intermedio'], ['F', 1, 'intermedio'],
        ['F', 0, 'dificil'], ['C', 1, 'dificil'], ['B', -1, 'dificil'],
        ['G', 1, 'dificil'], ['E', -1, 'dificil'],
    ];

    private const MODOS_MENORES = ['menor_natural' => 'menor natural', 'menor_armonica' => 'menor armónica', 'menor_melodica' => 'menor melódica'];

    public function up(): void
    {
        // La mayor (cargada antes) se ubica en su lugar del círculo de quintas.
        DB::table('biblioteca_ejercicios')->where('categoria', 'escala')->where('nombre', 'La mayor')->where('orden', 30)->update(['orden' => 103]);

        $orden = 100;
        foreach (self::MAYORES as [$letra, $alter, $dificultad]) {
            $this->agregar($letra, $alter, 'mayor', 'mayor', $dificultad, $orden++);
        }

        $orden = 200;
        foreach (self::MENORES as [$letra, $alter, $dificultad]) {
            foreach (self::MODOS_MENORES as $modo => $texto) {
                $nivel = $modo === 'menor_natural' ? $dificultad : ($dificultad === 'facil' ? 'intermedio' : 'dificil');
                $this->agregar($letra, $alter, $modo, $texto, $nivel, $orden++);
            }
        }
    }

    public function down(): void
    {
        ReversionSegura::verificar('2026_10_14_000001_msea_escalas_mayores_menores');

        foreach ($this->nombres() as $nombre) {
            if ($nombre === 'La mayor') {
                continue; // la cargó la migración anterior
            }
            $id = DB::table('biblioteca_ejercicios')->where('categoria', 'escala')->where('instrumento', 'violin')->where('nombre', $nombre)->value('id_biblioteca');
            if ($id && ! DB::table('tareas')->where('id_biblioteca', $id)->exists()) {
                DB::table('biblioteca_ejercicios')->where('id_biblioteca', $id)->delete();
            }
        }
    }

    private function agregar(string $letra, int $alter, string $modo, string $textoModo, string $dificultad, int $orden): void
    {
        $nombre = $this->nombre($letra, $alter, $textoModo);
        $existe = DB::table('biblioteca_ejercicios')->where('categoria', 'escala')->where('instrumento', 'violin')->where('nombre', $nombre)->exists();
        if ($existe) {
            return;
        }

        $clase = self::CLASE[$letra] + $alter;            // 7 = Sol ... 11 = Si (La♭ = 8, Si♭ = 10)
        $grave = $clase >= 7 ? 3 : 4;                      // tónica más grave desde Sol3
        $unaOctava = ($letra === 'A' && $alter === 0) ? 4 : $grave;

        DB::table('biblioteca_ejercicios')->insert([
            'categoria' => 'escala',
            'instrumento' => 'violin',
            'nombre' => $nombre,
            'dificultad' => $dificultad,
            'descripcion' => null,
            'datos' => json_encode([
                'tonica' => $letra,
                'alteracion' => $alter,
                'modo' => $modo,
                'inicio' => ['1' => $unaOctava, '2' => $grave, '3' => $grave],
                'octavas_max' => 3,
            ], JSON_UNESCAPED_UNICODE),
            'activo' => true,
            'orden' => $orden,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function nombre(string $letra, int $alter, string $textoModo): string
    {
        return self::SOLFEO[$letra].($alter > 0 ? '♯' : ($alter < 0 ? '♭' : '')).' '.$textoModo;
    }

    private function nombres(): array
    {
        $n = array_map(fn ($m) => $this->nombre($m[0], $m[1], 'mayor'), self::MAYORES);
        foreach (self::MENORES as $m) {
            foreach (self::MODOS_MENORES as $texto) {
                $n[] = $this->nombre($m[0], $m[1], $texto);
            }
        }

        return $n;
    }
};
