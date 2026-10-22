<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Escalas de la biblioteca técnica: notas reales, frecuencias exactas y ritmo.
 *
 * Afinación: temperamento igual con La4 = 440 Hz (la referencia de la fundación).
 *   f = 440 · 2^((midi − 69) / 12)
 * Ningún valor se aproxima por tabla: cada frecuencia se calcula y se muestra con 2 decimales.
 *
 * Ortografía: cada grado avanza una letra (A B C D E F G), así La mayor se escribe
 * La Si Do♯ Re Mi Fa♯ Sol♯ La y no "Re♭" en lugar de "Do♯".
 */
class EscalaService
{
    /** Intervalos en semitonos (ascendente y descendente). */
    public const MODOS = [
        'mayor' => ['nombre' => 'mayor', 'sube' => [2, 2, 1, 2, 2, 2, 1]],
        'menor_natural' => ['nombre' => 'menor natural', 'sube' => [2, 1, 2, 2, 1, 2, 2]],
        'menor_armonica' => ['nombre' => 'menor armónica', 'sube' => [2, 1, 2, 2, 1, 3, 1]],
        'menor_melodica' => ['nombre' => 'menor melódica', 'sube' => [2, 1, 2, 2, 2, 2, 1], 'baja' => [2, 1, 2, 2, 1, 2, 2]],
    ];

    /** Duración en pulsos de negra. */
    public const FIGURAS = [
        'redonda' => 4,
        'blanca_puntillo' => 3,
        'blanca' => 2,
        'negra_puntillo' => 1.5,
        'negra' => 1,
        'corchea_puntillo' => 0.75,
        'corchea' => 0.5,
        'semicorchea' => 0.25,
    ];

    public const NOMBRE_FIGURA = [
        'redonda' => ['redonda', 'redondas'],
        'blanca_puntillo' => ['blanca con puntillo', 'blancas con puntillo'],
        'blanca' => ['blanca', 'blancas'],
        'negra_puntillo' => ['negra con puntillo', 'negras con puntillo'],
        'negra' => ['negra', 'negras'],
        'corchea_puntillo' => ['corchea con puntillo', 'corcheas con puntillo'],
        'corchea' => ['corchea', 'corcheas'],
        'semicorchea' => ['semicorchea', 'semicorcheas'],
    ];

    public const COMPASES = ['2/4', '3/4', '4/4', '2/2', '6/8'];

    public const TOLERANCIAS = [5, 10, 15, 20, 25];

    public const TOLERANCIA_POR_DIFICULTAD = ['facil' => 15, 'intermedio' => 10, 'dificil' => 5];

    public const DIFICULTADES = ['facil' => 'Fácil', 'intermedio' => 'Intermedio', 'dificil' => 'Difícil'];

    /**
     * Instrumentos de cuerda de la fundación.
     *  - cuerdas: al aire, de grave a agudo (MIDI, sonido real).
     *  - min/max: registro aceptado para las escalas (sonido real).
     *  - clave: sol, do (viola) o fa (violonchelo y contrabajo).
     *  - escribe: el contrabajo se escribe una octava más aguda de lo que suena.
     *  - primera: semitonos que cubre la mano sobre cada cuerda en primera posición.
     */
    public const INSTRUMENTOS = [
        'violin' => ['nombre' => 'Violín', 'clave' => 'sol', 'cuerdas' => ['Sol' => 55, 'Re' => 62, 'La' => 69, 'Mi' => 76], 'min' => 55, 'max' => 103, 'escribe' => 0, 'familia' => 'violin', 'primera' => 7],
        'viola' => ['nombre' => 'Viola', 'clave' => 'do', 'cuerdas' => ['Do' => 48, 'Sol' => 55, 'Re' => 62, 'La' => 69], 'min' => 48, 'max' => 93, 'escribe' => 0, 'familia' => 'violin', 'primera' => 7],
        'violonchelo' => ['nombre' => 'Violonchelo', 'clave' => 'fa', 'cuerdas' => ['Do' => 36, 'Sol' => 43, 'Re' => 50, 'La' => 57], 'min' => 36, 'max' => 88, 'escribe' => 0, 'familia' => 'chelo', 'primera' => 6],
        'contrabajo' => ['nombre' => 'Contrabajo', 'clave' => 'fa', 'cuerdas' => ['Mi' => 28, 'La' => 33, 'Re' => 38, 'Sol' => 43], 'min' => 28, 'max' => 72, 'escribe' => 12, 'familia' => 'bajo', 'primera' => 4],
    ];

    /** Compatibilidad: registro del violín. */
    public const MIDI_MIN = 55;

    public const MIDI_MAX = 103;

    /** Clave del instrumento a partir del nombre guardado en la base ("Violín", "Chelo", "Contrabajo"...). */
    public static function instrumentoDe(?string $nombre): ?string
    {
        $t = mb_strtolower(trim((string) $nombre));
        $t = strtr($t, ['í' => 'i', 'á' => 'a', 'é' => 'e', 'ó' => 'o', 'ú' => 'u']);

        return match (true) {
            str_contains($t, 'contrabajo') || $t === 'bajo' => 'contrabajo',
            str_contains($t, 'chelo') || str_contains($t, 'cello') => 'violonchelo',
            str_contains($t, 'viola') => 'viola',
            str_contains($t, 'violin') => 'violin',
            default => null,
        };
    }

    /** Instrumentos de cuerda de un usuario (claves), en orden alfabético del nombre. */
    public function instrumentosDeUsuario(int $idUsuario): array
    {
        return DB::table('usuario_instrumento as ui')
            ->join('instrumentos as i', 'i.id_instrumento', '=', 'ui.id_instrumento')
            ->where('ui.id_usuario', $idUsuario)
            ->orderBy('i.nombre')
            ->pluck('i.nombre')
            ->map(fn ($n) => self::instrumentoDe($n))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** La misma escala (mismo nombre) en otro instrumento, si la biblioteca la tiene. */
    public function escalaParaInstrumento(object $escala, string $instrumento): ?object
    {
        if ($escala->instrumento === $instrumento) {
            return $escala;
        }
        $id = DB::table('biblioteca_ejercicios')->where('categoria', 'escala')->where('instrumento', $instrumento)
            ->where('nombre', $escala->nombre)->value('id_biblioteca');

        return $id ? $this->escala((int) $id, soloActivas: false) : null;
    }

    private const LETRAS = ['C', 'D', 'E', 'F', 'G', 'A', 'B'];

    private const CLASE = ['C' => 0, 'D' => 2, 'E' => 4, 'F' => 5, 'G' => 7, 'A' => 9, 'B' => 11];

    private const SOLFEO = ['C' => 'Do', 'D' => 'Re', 'E' => 'Mi', 'F' => 'Fa', 'G' => 'Sol', 'A' => 'La', 'B' => 'Si'];

    /** Posición de cada letra natural en el círculo de quintas (Do = 0). */
    private const QUINTAS = ['F' => -1, 'C' => 0, 'G' => 1, 'D' => 2, 'A' => 3, 'E' => 4, 'B' => 5];

    // ------------------------------------------------------------------
    // Biblioteca
    // ------------------------------------------------------------------

    public function escala(int $id, bool $soloActivas = true): ?object
    {
        $q = DB::table('biblioteca_ejercicios')->where('id_biblioteca', $id)->where('categoria', 'escala');
        if ($soloActivas) {
            $q->where('activo', true);
        }
        $fila = $q->first();
        if ($fila) {
            $fila->datos = $this->decodificar($fila->datos) + ['instrumento' => $fila->instrumento];
        }

        return $fila;
    }

    /** Escalas para el profesor (activas) o para el director (todas). */
    public function catalogo(string $instrumento = 'violin', bool $incluirInactivas = false): array
    {
        $q = DB::table('biblioteca_ejercicios')
            ->where('categoria', 'escala')
            ->where('instrumento', $instrumento)
            ->orderByRaw("case dificultad when 'facil' then 0 when 'intermedio' then 1 else 2 end")
            ->orderBy('orden')
            ->orderBy('nombre');
        if (! $incluirInactivas) {
            $q->where('activo', true);
        }

        return $q->get()->map(fn ($f) => $this->resumen($f))->all();
    }

    public function resumen(object $fila): array
    {
        $datos = (is_array($fila->datos) ? $fila->datos : $this->decodificar($fila->datos)) + ['instrumento' => $fila->instrumento ?? 'violin'];
        $octavasMax = (int) ($datos['octavas_max'] ?? 1);
        $notas = $this->notas($datos, 1, 'subir');

        return [
            'id' => (int) $fila->id_biblioteca,
            'instrumento' => $datos['instrumento'],
            'nombre' => $fila->nombre,
            'titulo' => 'Escala de '.$fila->nombre,
            'dificultad' => $fila->dificultad,
            'dificultad_texto' => self::DIFICULTADES[$fila->dificultad] ?? $fila->dificultad,
            'descripcion' => $fila->descripcion,
            'activo' => (bool) $fila->activo,
            'tonica' => $datos['tonica'] ?? 'C',
            'alteracion' => (int) ($datos['alteracion'] ?? 0),
            'modo' => $datos['modo'] ?? 'mayor',
            'modo_texto' => self::MODOS[$datos['modo'] ?? 'mayor']['nombre'] ?? '',
            'inicio' => $datos['inicio'] ?? [],
            'octavas_max' => $octavasMax,
            'tiene_digitacion' => array_values(array_map('strval', array_filter(array_keys($datos['inicio'] ?? []), fn ($o) => $this->cabeEnPrimeraPosicion($datos, (int) $o)))),
            'notas_una_octava' => array_map(fn ($n) => $n['etiqueta'], $notas),
            'armadura' => $this->armadura($datos),
            'tolerancia_sugerida' => self::TOLERANCIA_POR_DIFICULTAD[$fila->dificultad] ?? 10,
            'rangos' => collect($datos['inicio'] ?? [])->mapWithKeys(function ($_, $oct) use ($datos) {
                $n = $this->notas($datos, (int) $oct, 'subir');

                return [(string) $oct => $n[0]['etiqueta'].' a '.end($n)['etiqueta']];
            })->all(),
        ];
    }

    // ------------------------------------------------------------------
    // Teoría: notas, frecuencias y armadura
    // ------------------------------------------------------------------

    public static function frecuencia(int $midi): float
    {
        return round(440 * (2 ** (($midi - 69) / 12)), 2);
    }

    /**
     * Notas de la escala para N octavas.
     * $direccion: 'subir' (tónica a tónica) o 'subir_bajar' (sube y vuelve sin repetir la nota más aguda).
     */
    public function notas(array $datos, int $octavas, string $direccion = 'subir_bajar', bool $conDigitacion = true): array
    {
        $modo = self::MODOS[$datos['modo'] ?? 'mayor'] ?? self::MODOS['mayor'];
        $letra = strtoupper((string) ($datos['tonica'] ?? 'C'));
        $alter = (int) ($datos['alteracion'] ?? 0);
        $octava = (int) (($datos['inicio'][(string) $octavas] ?? $datos['inicio'][$octavas] ?? 4));

        $actual = ['letra' => $letra, 'octava' => $octava, 'midi' => 12 * ($octava + 1) + self::CLASE[$letra] + $alter];
        $sube = [$this->nota($actual)];

        for ($i = 0; $i < 7 * $octavas; $i++) {
            $actual = $this->siguiente($actual, $modo['sube'][$i % 7], +1);
            $sube[] = $this->nota($actual);
        }

        if ($direccion !== 'subir_bajar') {
            return $conDigitacion ? $this->conDigitacion($sube, $datos['instrumento'] ?? 'violin') : $sube;
        }

        // Bajada: intervalos descendentes (iguales a los ascendentes salvo en la menor melódica).
        $bajaIntervalos = array_reverse($modo['baja'] ?? $modo['sube']);
        $baja = [];
        for ($i = 0; $i < 7 * $octavas; $i++) {
            $actual = $this->siguiente($actual, $bajaIntervalos[$i % 7], -1);
            $baja[] = $this->nota($actual);
        }

        $todas = array_merge($sube, $baja);

        return $conDigitacion ? $this->conDigitacion($todas, $datos['instrumento'] ?? 'violin') : $todas;
    }

    /** Armadura de clave según el círculo de quintas. */
    public function armadura(array $datos): array
    {
        $letra = strtoupper((string) ($datos['tonica'] ?? 'C'));
        $quintas = self::QUINTAS[$letra] + 7 * (int) ($datos['alteracion'] ?? 0);
        if (str_starts_with((string) ($datos['modo'] ?? 'mayor'), 'menor')) {
            $quintas -= 3; // relativa mayor a una tercera menor arriba
        }

        if ($quintas === 0 || abs($quintas) > 7) {
            return ['tipo' => null, 'cantidad' => 0, 'letras' => [], 'texto' => 'Sin alteraciones'];
        }

        $orden = $quintas > 0 ? ['F', 'C', 'G', 'D', 'A', 'E', 'B'] : ['B', 'E', 'A', 'D', 'G', 'C', 'F'];
        $n = abs($quintas);

        return [
            'tipo' => $quintas > 0 ? 'sostenidos' : 'bemoles',
            'cantidad' => $n,
            'letras' => array_slice($orden, 0, $n),
            'texto' => $n.' '.($quintas > 0 ? ($n === 1 ? 'sostenido' : 'sostenidos') : ($n === 1 ? 'bemol' : 'bemoles'))
                .': '.implode(', ', array_map(fn ($l) => self::SOLFEO[$l].($quintas > 0 ? '♯' : '♭'), array_slice($orden, 0, $n))),
        ];
    }

    public static function nombreTonica(string $letra, int $alteracion): string
    {
        return (self::SOLFEO[strtoupper($letra)] ?? $letra).self::signo($alteracion);
    }

    private function siguiente(array $nota, int $semitonos, int $sentido): array
    {
        $idx = array_search($nota['letra'], self::LETRAS, true);
        $nuevoIdx = $idx + $sentido;
        $octava = $nota['octava'];
        if ($nuevoIdx > 6) {
            $nuevoIdx = 0;
            $octava++;
        } elseif ($nuevoIdx < 0) {
            $nuevoIdx = 6;
            $octava--;
        }

        return ['letra' => self::LETRAS[$nuevoIdx], 'octava' => $octava, 'midi' => $nota['midi'] + $sentido * $semitonos];
    }

    public function nota(array $n): array
    {
        $natural = 12 * ($n['octava'] + 1) + self::CLASE[$n['letra']];
        $alter = $n['midi'] - $natural;
        $nombre = self::SOLFEO[$n['letra']].self::signo($alter);

        return [
            'letra' => $n['letra'],
            'alteracion' => $alter,
            'octava' => $n['octava'],
            'midi' => $n['midi'],
            'nombre' => $nombre,
            'etiqueta' => $nombre.$n['octava'],
            'frecuencia' => self::frecuencia($n['midi']),
        ];
    }

    private static function signo(int $alter): string
    {
        return match (true) {
            $alter === 2 => '𝄪',
            $alter === -2 => '𝄫',
            $alter > 0 => str_repeat('♯', $alter),
            $alter < 0 => str_repeat('♭', -$alter),
            default => '',
        };
    }

    /** Dedo y variante según los semitonos sobre la cuerda al aire, en primera posición. */
    private const DEDOS = [
        // Violín y viola: cuerdas en quintas, cuatro dedos sobre 7 semitonos.
        'violin' => [0 => [0, null], 1 => [1, 'bajo'], 2 => [1, null], 3 => [2, 'bajo'], 4 => [2, 'alto'], 5 => [3, null], 6 => [3, 'alto'], 7 => [4, null]],
        // Violonchelo: un dedo por semitono (1 a un tono de la cuerda), 1 bajo y 4 extendido.
        'chelo' => [0 => [0, null], 1 => [1, 'bajo'], 2 => [1, null], 3 => [2, null], 4 => [3, null], 5 => [4, null], 6 => [4, 'extendido']],
        // Contrabajo (sistema 1-2-4): cuerdas en cuartas; el semitono sobre la cuerda es media posición.
        'bajo' => [0 => [0, null], 1 => [1, 'media posición'], 2 => [1, null], 3 => [2, null], 4 => [4, null]],
    ];

    private static function instrumento(array $datos): array
    {
        return self::INSTRUMENTOS[$datos['instrumento'] ?? 'violin'] ?? self::INSTRUMENTOS['violin'];
    }

    /** Nota más aguda que se alcanza en primera posición en ese instrumento. */
    public static function maxPrimeraPosicion(string $instrumento = 'violin'): int
    {
        $i = self::INSTRUMENTOS[$instrumento] ?? self::INSTRUMENTOS['violin'];

        return max($i['cuerdas']) + $i['primera'];
    }

    /**
     * Cuerda y dedo en primera posición. Regla: la cuerda más aguda posible (las notas
     * iguales a una cuerda al aire se tocan al aire, como en las escalas de los métodos).
     * Si la escala necesita cambios de posición, no se indica nada.
     */
    public function digitacion(int $midi, ?string $letra = null, string $instrumento = 'violin'): ?array
    {
        $inst = self::INSTRUMENTOS[$instrumento] ?? self::INSTRUMENTOS['violin'];
        if ($midi < $inst['min'] || $midi > self::maxPrimeraPosicion($instrumento)) {
            return null;
        }
        $cuerda = array_key_first($inst['cuerdas']);
        foreach ($inst['cuerdas'] as $nombre => $alAire) {
            if ($midi >= $alAire) {
                $cuerda = $nombre;
            }
        }
        $semitonos = $midi - $inst['cuerdas'][$cuerda];
        $tabla = self::DEDOS[$inst['familia']];
        if (! isset($tabla[$semitonos])) {
            return null;
        }
        [$dedo, $variante] = $tabla[$semitonos];

        // Violín y viola, seis semitonos: Do♯, Sol♯, Re♯... con 3 alto; Re♭, La♭, Mi♭, Si♭
        // (la letra de la cuerda siguiente) con 4 bajo, como en los métodos.
        if ($inst['familia'] === 'violin' && $semitonos === 6) {
            $nombres = array_keys($inst['cuerdas']);
            $siguiente = $nombres[array_search($cuerda, $nombres, true) + 1] ?? null;
            $letraSiguiente = $siguiente ? array_search($siguiente, self::SOLFEO, true) : ($cuerda === 'Mi' ? 'B' : 'E');
            if ($letra === $letraSiguiente) {
                [$dedo, $variante] = [4, 'bajo'];
            }
        }

        return ['cuerda' => $cuerda, 'dedo' => $dedo, 'variante' => $variante];
    }

    /** ¿La escala completa se puede tocar en primera posición? */
    public function cabeEnPrimeraPosicion(array $datos, int $octavas): bool
    {
        $notas = $this->notas($datos, $octavas, 'subir');

        return $notas && isset($notas[0]['cuerda']);
    }

    private function conDigitacion(array $notas, string $instrumento): array
    {
        $midis = array_column($notas, 'midi');
        $inst = self::INSTRUMENTOS[$instrumento] ?? self::INSTRUMENTOS['violin'];
        if (! $midis || min($midis) < $inst['min'] || max($midis) > self::maxPrimeraPosicion($instrumento)) {
            return $notas;
        }
        $con = [];
        foreach ($notas as $n) {
            $d = $this->digitacion($n['midi'], $n['letra'], $instrumento);
            if (! $d) {
                return $notas; // alguna nota pide otra posición: no se indica a medias
            }
            $con[] = $n + $d;
        }

        return $con;
    }

    // ------------------------------------------------------------------
    // Configuración que elige el profesor
    // ------------------------------------------------------------------

    /**
     * Valida y normaliza lo que el profesor indica para la escala.
     *
     * @throws ValidationException
     */
    public function normalizarConfig(mixed $entrada, object $escala): array
    {
        if (is_string($entrada)) {
            $entrada = json_decode($entrada, true);
        }
        if (! is_array($entrada)) {
            throw ValidationException::withMessages(['config_escala' => 'Indica cómo debe tocar la escala.']);
        }

        $datos = is_array($escala->datos) ? $escala->datos : $this->decodificar($escala->datos);
        $octavasMax = max(1, (int) ($datos['octavas_max'] ?? 1));
        $errores = [];

        $octavas = (int) ($entrada['octavas'] ?? 1);
        if ($octavas < 1 || $octavas > $octavasMax || ! isset($datos['inicio'][(string) $octavas])) {
            $errores['octavas'] = "Esta escala admite de 1 a {$octavasMax} octavas.";
        }

        $direccion = (string) ($entrada['direccion'] ?? 'subir_bajar');
        if (! in_array($direccion, ['subir_bajar', 'subir'], true)) {
            $errores['direccion'] = 'Elige si la escala sube y baja o solo sube.';
        }

        $bpm = (int) ($entrada['bpm'] ?? 60);
        if ($bpm < 30 || $bpm > 240) {
            $errores['bpm'] = 'El tempo debe estar entre 30 y 240 pulsos por minuto.';
        }

        $compas = (string) ($entrada['compas'] ?? '4/4');
        if (! in_array($compas, self::COMPASES, true)) {
            $errores['compas'] = 'Compás no válido.';
        }

        $tolerancia = (int) ($entrada['tolerancia'] ?? (self::TOLERANCIA_POR_DIFICULTAD[$escala->dificultad] ?? 10));
        if (! in_array($tolerancia, self::TOLERANCIAS, true)) {
            $errores['tolerancia'] = 'La tolerancia debe ser 5, 10, 15, 20 o 25 cents.';
        }

        [$ritmo, $errorRitmo] = $this->normalizarRitmo($entrada['ritmo'] ?? null);
        if ($errorRitmo) {
            $errores['ritmo'] = $errorRitmo;
        }

        if ($errores) {
            throw ValidationException::withMessages(collect($errores)->mapWithKeys(fn ($m, $k) => ["config_escala.$k" => $m])->all());
        }

        return [
            'octavas' => $octavas,
            'direccion' => $direccion,
            'bpm' => $bpm,
            'compas' => $compas,
            'tolerancia' => $tolerancia,
            'ritmo' => $ritmo,
            'digitacion' => filter_var($entrada['digitacion'] ?? true, FILTER_VALIDATE_BOOL),
        ];
    }

    /** @return array{0: array, 1: ?string} */
    private function normalizarRitmo(mixed $r): array
    {
        if (! is_array($r)) {
            return [['modo' => 'uniforme', 'figura' => 'negra', 'repeticiones' => 1], null];
        }

        if (($r['modo'] ?? '') === 'patron') {
            $celdas = $r['celdas'] ?? null;
            if (! is_array($celdas) || ! $celdas || count($celdas) > 16) {
                return [[], 'El ritmo propio necesita entre 1 y 16 células.'];
            }
            $limpias = [];
            foreach (array_values($celdas) as $i => $celda) {
                if (! is_array($celda) || ! $celda || count($celda) > 16) {
                    return [[], 'La célula '.($i + 1).' debe tener entre 1 y 16 figuras.'];
                }
                $figuras = [];
                foreach ($celda as $f) {
                    if (! is_string($f) || ! isset(self::FIGURAS[$f])) {
                        return [[], 'La célula '.($i + 1).' tiene una figura no válida.'];
                    }
                    $figuras[] = $f;
                }
                if ($this->pulsos($figuras) > 16) {
                    return [[], 'La célula '.($i + 1).' supera 16 pulsos por nota.'];
                }
                $limpias[] = $figuras;
            }

            return [['modo' => 'patron', 'celdas' => $limpias], null];
        }

        $figura = (string) ($r['figura'] ?? 'negra');
        $rep = (int) ($r['repeticiones'] ?? 1);
        if (! isset(self::FIGURAS[$figura])) {
            return [[], 'Figura rítmica no válida.'];
        }
        if ($rep < 1 || $rep > 16 || self::FIGURAS[$figura] * $rep > 16) {
            return [[], 'Usa entre 1 y 16 repeticiones por nota (máximo 16 pulsos).'];
        }

        return [['modo' => 'uniforme', 'figura' => $figura, 'repeticiones' => $rep], null];
    }

    /** Figuras que se tocan en cada nota (las células del patrón se repiten en ciclo). */
    public function ritmoPorNota(array $ritmo, int $cantidadNotas): array
    {
        $salida = [];
        for ($i = 0; $i < $cantidadNotas; $i++) {
            $salida[] = ($ritmo['modo'] ?? 'uniforme') === 'patron'
                ? $ritmo['celdas'][$i % count($ritmo['celdas'])]
                : array_fill(0, (int) $ritmo['repeticiones'], $ritmo['figura']);
        }

        return $salida;
    }

    public function pulsos(array $figuras): float
    {
        return array_sum(array_map(fn ($f) => self::FIGURAS[$f] ?? 0, $figuras));
    }

    public function describirRitmo(array $ritmo): string
    {
        if (($ritmo['modo'] ?? '') === 'patron') {
            $celdas = array_map(fn ($c) => implode(' + ', array_map(fn ($f) => self::NOMBRE_FIGURA[$f][0], $c)), $ritmo['celdas']);

            return count($celdas) === 1
                ? 'ritmo propio por nota: '.$celdas[0]
                : 'ritmo propio de '.count($celdas).' células: '.implode(' / ', $celdas);
        }
        $n = (int) $ritmo['repeticiones'];
        $nombre = self::NOMBRE_FIGURA[$ritmo['figura']][$n === 1 ? 0 : 1];

        return ($n === 1 ? 'una' : $n).' '.$nombre.' por nota';
    }

    /** Figura que marca el metrónomo en cada compás (2/2: blanca, 6/8: corchea, resto: negra). */
    public static function unidadPulso(string $compas): string
    {
        return match ($compas) {
            '2/2' => 'blanca',
            '6/8' => 'corchea',
            default => 'negra',
        };
    }

    public function titulo(object $escala, array $config): string
    {
        return mb_substr('Escala de '.$escala->nombre.' - '.$config['octavas'].' '.($config['octavas'] === 1 ? 'octava' : 'octavas'), 0, 150);
    }

    public function describir(object $escala, array $config): string
    {
        return implode(' · ', [
            'Escala de '.$escala->nombre,
            $config['octavas'].' '.($config['octavas'] === 1 ? 'octava' : 'octavas'),
            $config['direccion'] === 'subir' ? 'solo sube' : 'sube y baja',
            $this->describirRitmo($config['ritmo']),
            self::unidadPulso($config['compas']).' = '.$config['bpm'],
            'compás '.$config['compas'],
            'tolerancia ±'.$config['tolerancia'].' cents',
        ]);
    }

    /** Todo lo que necesita la página de práctica. */
    public function ejercicio(object $escala, array $config): array
    {
        $datos = (is_array($escala->datos) ? $escala->datos : $this->decodificar($escala->datos)) + ['instrumento' => $escala->instrumento ?? 'violin'];
        $claveInst = $datos['instrumento'];
        $inst = self::INSTRUMENTOS[$claveInst] ?? self::INSTRUMENTOS['violin'];

        // Una tarea pensada para violín puede pedir más octavas de las que tiene otro instrumento.
        $pedidas = (int) $config['octavas'];
        $octavasMax = max(1, (int) ($datos['octavas_max'] ?? 1));
        if ($pedidas > $octavasMax || ! isset($datos['inicio'][(string) $pedidas])) {
            $config['octavas'] = $octavasMax;
        }

        $notas = $this->notas($datos, $config['octavas'], $config['direccion']);
        // Posición en el pentagrama: el contrabajo se escribe una octava más aguda de lo que suena.
        foreach ($notas as &$n) {
            $n['octava_escrita'] = $n['octava'] + intdiv($inst['escribe'], 12);
        }
        unset($n);
        if (empty($config['digitacion'])) {
            $notas = array_map(fn ($n) => array_diff_key($n, ['cuerda' => 1, 'dedo' => 1, 'variante' => 1]), $notas);
        }

        return [
            'escala' => [
                'id' => (int) $escala->id_biblioteca,
                'nombre' => $escala->nombre,
                'dificultad' => self::DIFICULTADES[$escala->dificultad] ?? $escala->dificultad,
                'armadura' => $this->armadura($datos),
            ],
            'instrumento' => [
                'clave' => $claveInst,
                'nombre' => $inst['nombre'],
                'clef' => $inst['clave'],
                'escribe_octava_arriba' => $inst['escribe'] === 12,
                'cuerdas' => $inst['cuerdas'],
            ],
            'octavas_pedidas' => $pedidas,
            'config' => $config,
            'resumen' => $this->describir($escala, $config),
            'ritmo_texto' => $this->describirRitmo($config['ritmo']),
            'notas' => $notas,
            'ritmo' => $this->ritmoPorNota($config['ritmo'], count($notas)),
            'figuras' => self::FIGURAS,
            'unidad_pulso' => self::unidadPulso($config['compas']),
            'referencia_hz' => 440,
        ];
    }

    // ------------------------------------------------------------------
    // Resultado medido en el navegador del estudiante
    // ------------------------------------------------------------------

    /** Limpia el resultado: solo números acotados; se guarda marcado como no verificado. */
    public function normalizarResultado(array $entrada, int $totalNotas): array
    {
        $notas = [];
        foreach (array_slice((array) ($entrada['notas'] ?? []), 0, $totalNotas) as $n) {
            if (! is_array($n)) {
                continue;
            }
            $notas[] = [
                'cents' => round(max(-100, min(100, (float) ($n['cents'] ?? 0))), 1),
                'ms' => (int) max(0, min(3_600_000, (int) ($n['ms'] ?? 0))),
            ];
        }

        $completada = count($notas) === $totalNotas;
        $desvio = $notas ? round(array_sum(array_map(fn ($n) => abs($n['cents']), $notas)) / count($notas), 1) : null;

        return [
            'completada' => $completada,
            'notas_afinadas' => count($notas),
            'notas_total' => $totalNotas,
            'duracion_s' => (int) max(0, min(86_400, (int) ($entrada['duracion_s'] ?? 0))),
            'bpm' => isset($entrada['bpm']) ? (int) max(0, min(300, (int) $entrada['bpm'])) : null,
            'desvio_medio_cents' => $desvio,
            'notas' => $notas,
            'verificado' => false,
            'medido_en' => 'navegador',
        ];
    }

    public function textoResultado(array $r): string
    {
        $min = intdiv($r['duracion_s'], 60);
        $seg = $r['duracion_s'] % 60;
        $tiempo = $min ? "{$min} min {$seg} s" : "{$seg} s";

        return ($r['completada'] ? 'Escala completa' : "Escala incompleta ({$r['notas_afinadas']} de {$r['notas_total']} notas)")
            ." en {$tiempo}"
            .($r['desvio_medio_cents'] !== null ? ", desvío medio {$r['desvio_medio_cents']} cents" : '')
            .(! empty($r['bpm']) ? ", a tempo {$r['bpm']}" : '')
            .'. Medido en el navegador del estudiante (no verificado).';
    }

    // ------------------------------------------------------------------

    public function decodificar(mixed $json): array
    {
        if (is_array($json)) {
            return $json;
        }
        $datos = json_decode((string) $json, true);

        return is_array($datos) ? $datos : [];
    }

    /** Comprueba que la escala definida quede dentro del registro del instrumento. */
    public function validarRango(array $datos): ?string
    {
        $inst = self::instrumento($datos);
        foreach (array_keys($datos['inicio'] ?? []) as $oct) {
            $notas = $this->notas($datos, (int) $oct, 'subir', false);
            $min = min(array_column($notas, 'midi'));
            $max = max(array_column($notas, 'midi'));
            $texto = "En {$oct} ".($oct == 1 ? 'octava' : 'octavas');
            if ($min < $inst['min']) {
                return "{$texto} la escala baja de {$this->etiquetaMidi($inst['min'])}, la nota más grave {$this->articulo($inst['nombre'])}.";
            }
            if ($max > $inst['max']) {
                return "{$texto} la escala supera {$this->etiquetaMidi($inst['max'])}, el límite fijado para escalas {$this->articulo($inst['nombre'])}.";
            }
        }

        return null;
    }

    private function etiquetaMidi(int $midi): string
    {
        $nombres = ['Do', 'Do♯', 'Re', 'Mi♭', 'Mi', 'Fa', 'Fa♯', 'Sol', 'La♭', 'La', 'Si♭', 'Si'];

        return $nombres[$midi % 12].(intdiv($midi, 12) - 1);
    }

    private function articulo(string $nombre): string
    {
        return ($nombre === 'Viola' ? 'de la ' : 'del ').mb_strtolower($nombre);
    }
}
