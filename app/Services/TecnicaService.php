<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ejercicios técnicos de la biblioteca (no escalas):
 *  - tecnica_mano_izquierda: patrones de dedos generados para cada instrumento
 *    (trinos, 1-2-3-4, saltos...) sobre todas las cuerdas, para ganar velocidad y precisión.
 *  - metodo: ejercicios digitalizados de un método en PDF (secuencia de notas escrita).
 *
 * Producen el mismo formato que EscalaService::ejercicio(), así la práctica guiada
 * (nota objetivo, afinador, pentagrama y metrónomo) es la misma para todo.
 */
class TecnicaService
{
    public const CATEGORIAS = [
        'tecnica_mano_izquierda' => 'Técnica de mano izquierda',
        'metodo' => 'Métodos digitalizados',
    ];

    /** Patrones de dedos por familia (0 = cuerda al aire). Mismo nombre en todos los instrumentos cuando existe. */
    public const PATRONES = [
        'violin' => [
            'Al aire y dedos 0-1-2-3' => [[0, 1, 2, 3], 'facil'],
            'Trino 1-2' => [[1, 2, 1, 2], 'facil'],
            'Trino 2-3' => [[2, 3, 2, 3], 'intermedio'],
            'Trino 3-4' => [[3, 4, 3, 4], 'dificil'],
            'Dedos 1-2-3-4' => [[1, 2, 3, 4], 'intermedio'],
            'Dedos 4-3-2-1' => [[4, 3, 2, 1], 'intermedio'],
            'Escalera 1-2-3-4-3-2' => [[1, 2, 3, 4, 3, 2], 'intermedio'],
            'Saltos 1-3-2-4' => [[1, 3, 2, 4], 'dificil'],
        ],
        'bajo' => [
            'Al aire y dedos 0-1-2-4' => [[0, 1, 2, 4], 'facil'],
            'Trino 1-2' => [[1, 2, 1, 2], 'facil'],
            'Trino 2-4' => [[2, 4, 2, 4], 'intermedio'],
            'Dedos 1-2-4' => [[1, 2, 4, 2], 'intermedio'],
            'Dedos 4-2-1' => [[4, 2, 1, 2], 'intermedio'],
            'Saltos 1-4-2-4' => [[1, 4, 2, 4], 'dificil'],
        ],
    ];

    /** Marcos de la mano: semitonos de cada dedo sobre la cuerda al aire. */
    public const MARCOS = [
        'violin' => [
            '2 alto' => [1 => 2, 2 => 4, 3 => 5, 4 => 7],
            '2 bajo' => [1 => 2, 2 => 3, 3 => 5, 4 => 7],
            '1 bajo' => [1 => 1, 2 => 3, 3 => 5, 4 => 6],
            '3 alto' => [1 => 2, 2 => 4, 3 => 6, 4 => 7],
        ],
        'chelo' => [
            'primera posición' => [1 => 2, 2 => 3, 3 => 4, 4 => 5],
            'extensión' => [1 => 2, 2 => 4, 3 => 5, 4 => 6],
        ],
        'bajo' => [
            'primera posición' => [1 => 2, 2 => 3, 4 => 4],
            'media posición' => [1 => 1, 2 => 2, 4 => 3],
        ],
    ];

    public const FIGURAS_TECNICA = ['negra', 'corchea', 'semicorchea'];

    private const LETRAS = ['C', 'D', 'E', 'F', 'G', 'A', 'B'];

    private const CLASE = ['C' => 0, 'D' => 2, 'E' => 4, 'F' => 5, 'G' => 7, 'A' => 9, 'B' => 11];

    private const LETRA_CUERDA = ['Sol' => 'G', 'Re' => 'D', 'La' => 'A', 'Mi' => 'E', 'Do' => 'C'];

    public function __construct(private readonly EscalaService $escalas) {}

    // ------------------------------------------------------------------
    // Biblioteca
    // ------------------------------------------------------------------

    public function ejercicio(int $id, bool $soloActivos = true): ?object
    {
        $q = DB::table('biblioteca_ejercicios')->where('id_biblioteca', $id)->whereIn('categoria', array_keys(self::CATEGORIAS));
        if ($soloActivos) {
            $q->where('activo', true);
        }
        $fila = $q->first();
        if ($fila) {
            $fila->datos = $this->escalas->decodificar($fila->datos);
        }

        return $fila;
    }

    public function catalogo(string $instrumento, bool $incluirInactivos = false): array
    {
        $q = DB::table('biblioteca_ejercicios')
            ->whereIn('categoria', array_keys(self::CATEGORIAS))
            ->where('instrumento', $instrumento)
            ->orderBy('categoria')
            ->orderByRaw("case dificultad when 'facil' then 0 when 'intermedio' then 1 else 2 end")
            ->orderBy('orden')
            ->orderBy('nombre');
        if (! $incluirInactivos) {
            $q->where('activo', true);
        }

        return $q->get()->map(fn ($f) => $this->resumen($f))->all();
    }

    public function resumen(object $fila): array
    {
        $datos = is_array($fila->datos) ? $fila->datos : $this->escalas->decodificar($fila->datos);
        $notas = $this->notas($datos, $fila->instrumento);

        return [
            'id' => (int) $fila->id_biblioteca,
            'categoria' => $fila->categoria,
            'categoria_texto' => self::CATEGORIAS[$fila->categoria] ?? $fila->categoria,
            'instrumento' => $fila->instrumento,
            'nombre' => $fila->nombre,
            'titulo' => $fila->nombre,
            'dificultad' => $fila->dificultad,
            'dificultad_texto' => EscalaService::DIFICULTADES[$fila->dificultad] ?? $fila->dificultad,
            'descripcion' => $fila->descripcion,
            'activo' => (bool) $fila->activo,
            'cantidad_notas' => count($notas),
            'primeras_notas' => array_map(fn ($n) => $n['etiqueta'], array_slice($notas, 0, 8)),
            'fuente' => $datos['fuente'] ?? null,
            'compas' => $datos['compas'] ?? '4/4',
        ];
    }

    /** La misma técnica en otro instrumento (mismo nombre). Los métodos se tocan tal cual. */
    public function paraInstrumento(object $fila, string $instrumento): ?object
    {
        if ($fila->instrumento === $instrumento || $fila->categoria === 'metodo') {
            return $fila;
        }
        $id = DB::table('biblioteca_ejercicios')->where('categoria', $fila->categoria)
            ->where('instrumento', $instrumento)->where('nombre', $fila->nombre)->value('id_biblioteca');

        return $id ? $this->ejercicio((int) $id, soloActivos: false) : null;
    }

    // ------------------------------------------------------------------
    // Notas
    // ------------------------------------------------------------------

    /** Notas del ejercicio con frecuencia exacta (La4 = 440 Hz), figura y, si se conoce, cuerda y dedo. */
    public function notas(array $datos, string $instrumento): array
    {
        return match ($datos['tipo'] ?? '') {
            'patron_dedos' => $this->notasPatron($datos, $instrumento),
            'secuencia' => $this->notasSecuencia($datos, $instrumento),
            default => [],
        };
    }

    private function notasPatron(array $datos, string $instrumento): array
    {
        $inst = EscalaService::INSTRUMENTOS[$instrumento] ?? EscalaService::INSTRUMENTOS['violin'];
        $marco = self::MARCOS[$inst['familia']][$datos['marco'] ?? ''] ?? null;
        if (! $marco) {
            return [];
        }
        $figura = $datos['figura'] ?? 'corchea';
        $repeticiones = max(1, min(16, (int) ($datos['repeticiones'] ?? 4)));
        $notas = [];

        foreach ($inst['cuerdas'] as $cuerda => $alAire) {
            foreach (range(1, $repeticiones) as $_) {
                foreach ((array) ($datos['dedos'] ?? []) as $dedo) {
                    $semitonos = $dedo === 0 ? 0 : ($marco[$dedo] ?? null);
                    if ($semitonos === null) {
                        continue 3; // el patrón usa un dedo que este marco no tiene
                    }
                    $nota = $this->deletrear($alAire + $semitonos, $cuerda, $dedo, $inst['familia'], $semitonos);
                    $notas[] = $nota + ['cuerda' => $cuerda, 'dedo' => $dedo, 'variante' => null, 'figura' => $figura];
                }
            }
        }

        return $notas;
    }

    /**
     * Nombre correcto de la nota sobre una cuerda. En violín y viola cada dedo avanza una
     * letra (en la cuerda La: 1 = Si, 2 = Do o Do♯, 3 = Re o Re♯, 4 = Mi). En violonchelo
     * y contrabajo los dedos van por semitono y se escribe el intervalo sobre la cuerda al aire.
     */
    private function deletrear(int $midi, string $cuerda, int $dedo, string $familia, int $semitonos): array
    {
        $letraCuerda = self::LETRA_CUERDA[$cuerda];
        // Violín y viola: un dedo = una letra. Violonchelo y contrabajo: se escribe como el
        // intervalo sobre la cuerda (2.ª menor/mayor, 3.ª menor/mayor, 4.ª justa, 4.ª aumentada).
        $pasos = ($familia === 'violin' && $dedo > 0) ? $dedo : [0 => 0, 1 => 1, 2 => 1, 3 => 2, 4 => 2, 5 => 3, 6 => 3, 7 => 4][$semitonos];
        $idx = (array_search($letraCuerda, self::LETRAS, true) + $pasos) % 7;
        $letra = self::LETRAS[$idx];
        $octava = intdiv($midi, 12) - 1;
        $natural = 12 * ($octava + 1) + self::CLASE[$letra];
        if ($natural - $midi > 6) {
            $octava++;
        } elseif ($midi - $natural > 6) {
            $octava--;
        }

        return $this->escalas->nota(['letra' => $letra, 'octava' => $octava, 'midi' => $midi]);
    }

    private function notasSecuencia(array $datos, string $instrumento): array
    {
        $notas = [];
        foreach ((array) ($datos['notas'] ?? []) as $n) {
            if (! empty($n['silencio']) || empty($n['letra'])) {
                continue; // los silencios no se afinan
            }
            $letra = strtoupper((string) $n['letra']);
            if (! isset(self::CLASE[$letra])) {
                continue;
            }
            $octava = (int) $n['octava'];
            $alter = (int) ($n['alteracion'] ?? 0);
            $midi = 12 * ($octava + 1) + self::CLASE[$letra] + $alter;
            $nota = $this->escalas->nota(['letra' => $letra, 'octava' => $octava, 'midi' => $midi]);
            $figura = (string) ($n['figura'] ?? 'negra');
            $nota['figura'] = isset(EscalaService::FIGURAS[$figura]) ? $figura : 'negra';
            if (isset($n['dedo']) && $n['dedo'] !== null && $n['dedo'] !== '') {
                $nota['dedo'] = (int) $n['dedo'];
                $nota['cuerda'] = $n['cuerda'] ?? null;
                $nota['variante'] = null;
            }
            $notas[] = $nota;
        }

        return $notas;
    }

    // ------------------------------------------------------------------
    // Configuración del profesor y ejercicio para la práctica
    // ------------------------------------------------------------------

    /**
     * modo: 'afinacion' (cada nota se sostiene afinada antes de avanzar) o 'velocidad'
     * (se toca al tempo; cada nota debe sonar afinada aunque sea breve y el tempo sube de a poco
     * desde bpm hasta bpm_objetivo).
     */
    public function normalizarConfig(mixed $entrada, object $ejercicio): array
    {
        if (is_string($entrada)) {
            $entrada = json_decode($entrada, true);
        }
        $entrada = is_array($entrada) ? $entrada : [];
        $errores = [];

        $modo = (string) ($entrada['modo'] ?? 'velocidad');
        if (! in_array($modo, ['afinacion', 'velocidad'], true)) {
            $errores['modo'] = 'Elige afinación o velocidad.';
        }
        $bpm = (int) ($entrada['bpm'] ?? 60);
        $objetivo = (int) ($entrada['bpm_objetivo'] ?? max($bpm, 100));
        if ($bpm < 30 || $bpm > 240) {
            $errores['bpm'] = 'El tempo inicial debe estar entre 30 y 240.';
        }
        if ($objetivo < $bpm || $objetivo > 240) {
            $errores['bpm_objetivo'] = 'La meta de tempo debe ser mayor o igual al tempo inicial (hasta 240).';
        }
        $paso = (int) ($entrada['paso'] ?? 4);
        if ($paso < 1 || $paso > 20) {
            $errores['paso'] = 'El aumento de tempo debe estar entre 1 y 20.';
        }
        $compas = (string) ($entrada['compas'] ?? ($ejercicio->datos['compas'] ?? '4/4'));
        if (! in_array($compas, EscalaService::COMPASES, true)) {
            $errores['compas'] = 'Compás no válido.';
        }
        $tolerancia = (int) ($entrada['tolerancia'] ?? (EscalaService::TOLERANCIA_POR_DIFICULTAD[$ejercicio->dificultad] ?? 10));
        if (! in_array($tolerancia, EscalaService::TOLERANCIAS, true)) {
            $errores['tolerancia'] = 'La tolerancia debe ser 5, 10, 15, 20 o 25 cents.';
        }
        $figura = $entrada['figura'] ?? null;
        if ($figura !== null && ! in_array($figura, self::FIGURAS_TECNICA, true)) {
            $errores['figura'] = 'Figura no válida.';
        }

        if ($errores) {
            throw ValidationException::withMessages(collect($errores)->mapWithKeys(fn ($m, $k) => ["config_escala.$k" => $m])->all());
        }

        return [
            'tipo' => 'tecnica',
            'modo' => $modo,
            'bpm' => $bpm,
            'bpm_objetivo' => $objetivo,
            'paso' => $paso,
            'compas' => $compas,
            'tolerancia' => $tolerancia,
            'figura' => $figura,
            'digitacion' => filter_var($entrada['digitacion'] ?? true, FILTER_VALIDATE_BOOL),
        ];
    }

    public function titulo(object $ejercicio, array $config): string
    {
        return mb_substr($ejercicio->nombre.($config['modo'] === 'velocidad' ? ' - velocidad' : ''), 0, 150);
    }

    public function describir(object $ejercicio, array $config): string
    {
        return implode(' · ', array_filter([
            $ejercicio->nombre,
            $config['modo'] === 'velocidad'
                ? "velocidad: de {$config['bpm']} a {$config['bpm_objetivo']} (sube {$config['paso']})"
                : 'afinación nota por nota, negra = '.$config['bpm'],
            $config['figura'] ? EscalaService::NOMBRE_FIGURA[$config['figura']][1] : null,
            'tolerancia ±'.$config['tolerancia'].' cents',
        ]));
    }

    /** Todo lo que necesita la página de práctica (mismo formato que una escala). */
    public function ejercicioPractica(object $fila, array $config): array
    {
        $datos = is_array($fila->datos) ? $fila->datos : $this->escalas->decodificar($fila->datos);
        $claveInst = $fila->instrumento;
        $inst = EscalaService::INSTRUMENTOS[$claveInst] ?? EscalaService::INSTRUMENTOS['violin'];
        if ($config['figura'] && ($datos['tipo'] ?? '') === 'patron_dedos') {
            $datos['figura'] = $config['figura'];
        }

        $notas = $this->notas($datos, $claveInst);
        foreach ($notas as &$n) {
            $n['octava_escrita'] = $n['octava'] + intdiv($inst['escribe'], 12);
        }
        unset($n);
        if (empty($config['digitacion'])) {
            $notas = array_map(fn ($n) => array_diff_key($n, ['cuerda' => 1, 'dedo' => 1, 'variante' => 1]), $notas);
        }

        $armadura = isset($datos['fifths']) ? $this->armaduraDeQuintas((int) $datos['fifths']) : ['tipo' => null, 'cantidad' => 0, 'letras' => [], 'texto' => 'Sin armadura'];

        return [
            'tipo' => 'tecnica',
            'escala' => [
                'id' => (int) $fila->id_biblioteca,
                'nombre' => $fila->nombre,
                'dificultad' => EscalaService::DIFICULTADES[$fila->dificultad] ?? $fila->dificultad,
                'armadura' => $armadura,
                'categoria' => self::CATEGORIAS[$fila->categoria] ?? $fila->categoria,
                'fuente' => $datos['fuente'] ?? null,
            ],
            'instrumento' => [
                'clave' => $claveInst,
                'nombre' => $inst['nombre'],
                'clef' => $inst['clave'],
                'escribe_octava_arriba' => $inst['escribe'] === 12,
                'cuerdas' => $inst['cuerdas'],
            ],
            'octavas_pedidas' => null,
            'config' => $config + ['octavas' => null, 'direccion' => null],
            'resumen' => $this->describir($fila, $config),
            'ritmo_texto' => $config['figura'] ? EscalaService::NOMBRE_FIGURA[$config['figura']][1] : 'ritmo escrito',
            'notas' => $notas,
            'ritmo' => array_map(fn ($n) => [$n['figura'] ?? 'negra'], $notas),
            'figuras' => EscalaService::FIGURAS,
            'unidad_pulso' => EscalaService::unidadPulso($config['compas']),
            'referencia_hz' => 440,
            'velocidad' => [
                'modo' => $config['modo'],
                'bpm_inicial' => $config['bpm'],
                'bpm_objetivo' => $config['bpm_objetivo'],
                'paso' => $config['paso'],
            ],
        ];
    }

    public function armaduraDeQuintas(int $quintas): array
    {
        if ($quintas === 0 || abs($quintas) > 7) {
            return ['tipo' => null, 'cantidad' => 0, 'letras' => [], 'texto' => 'Sin alteraciones'];
        }
        $orden = $quintas > 0 ? ['F', 'C', 'G', 'D', 'A', 'E', 'B'] : ['B', 'E', 'A', 'D', 'G', 'C', 'F'];
        $solfeo = ['C' => 'Do', 'D' => 'Re', 'E' => 'Mi', 'F' => 'Fa', 'G' => 'Sol', 'A' => 'La', 'B' => 'Si'];
        $n = abs($quintas);
        $letras = array_slice($orden, 0, $n);

        return [
            'tipo' => $quintas > 0 ? 'sostenidos' : 'bemoles',
            'cantidad' => $n,
            'letras' => $letras,
            'texto' => $n.' '.($quintas > 0 ? ($n === 1 ? 'sostenido' : 'sostenidos') : ($n === 1 ? 'bemol' : 'bemoles'))
                .': '.implode(', ', array_map(fn ($l) => $solfeo[$l].($quintas > 0 ? '♯' : '♭'), $letras)),
        ];
    }

    // ------------------------------------------------------------------
    // Semilla: ejercicios de dedos para los cuatro instrumentos
    // ------------------------------------------------------------------

    /** Filas listas para insertar (las usa la migración). */
    public static function semilla(): array
    {
        $filas = [];
        foreach (EscalaService::INSTRUMENTOS as $clave => $inst) {
            $familiaPatron = $inst['familia'] === 'bajo' ? 'bajo' : 'violin';
            $marcos = array_keys(self::MARCOS[$inst['familia']]);
            $orden = 100;
            foreach ($marcos as $m => $marco) {
                foreach (self::PATRONES[$familiaPatron] as $nombre => [$dedos, $dificultad]) {
                    if ($m > 0 && $dificultad === 'facil') {
                        $dificultad = 'intermedio';
                    } elseif ($m > 0 && $dificultad === 'intermedio') {
                        $dificultad = 'dificil';
                    }
                    $filas[] = [
                        'categoria' => 'tecnica_mano_izquierda',
                        'instrumento' => $clave,
                        'nombre' => $nombre.' · '.$marco,
                        'dificultad' => $dificultad,
                        'descripcion' => 'Patrón '.implode('-', $dedos).' repetido 4 veces en cada cuerda, de la más grave a la más aguda, con la mano en '.$marco.'. Para velocidad y precisión de los dedos.',
                        'datos' => json_encode([
                            'tipo' => 'patron_dedos',
                            'dedos' => $dedos,
                            'marco' => $marco,
                            'repeticiones' => 4,
                            'figura' => 'corchea',
                            'compas' => '4/4',
                        ], JSON_UNESCAPED_UNICODE),
                        'activo' => true,
                        'orden' => $orden++,
                    ];
                }
            }
        }

        return $filas;
    }
}
