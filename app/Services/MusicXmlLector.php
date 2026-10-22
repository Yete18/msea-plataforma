<?php

namespace App\Services;

/**
 * Lee el MusicXML que produce el reconocimiento óptico (OMR) y lo divide en ejercicios.
 *
 * - Un ejercicio termina en una doble barra o barra final (light-light / light-heavy),
 *   que es como se separan los ejercicios numerados en los métodos.
 * - Se toma la primera voz de la primera parte; en acordes, la nota superior.
 * - El XML se carga sin red ni entidades externas.
 */
class MusicXmlLector
{
    private const FIGURA = [
        'whole' => 'redonda', 'half' => 'blanca', 'quarter' => 'negra',
        'eighth' => 'corchea', '16th' => 'semicorchea',
    ];

    private const PUNTILLO = ['redonda' => null, 'blanca' => 'blanca_puntillo', 'negra' => 'negra_puntillo', 'corchea' => 'corchea_puntillo', 'semicorchea' => null];

    /** @return array<int, array{numero:int, compas:string, fifths:int, clave:string, notas:array, compases:int}> */
    public function ejercicios(string $xml): array
    {
        $previo = libxml_use_internal_errors(true);
        $doc = new \DOMDocument;
        $ok = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        if (! $ok) {
            return [];
        }

        $xp = new \DOMXPath($doc);
        $parte = $xp->query('/score-partwise/part')->item(0);
        if (! $parte) {
            return [];
        }

        $ejercicios = [];
        $actual = $this->nuevo(1, '4/4', 0, 'sol');
        $compas = '4/4';
        $fifths = 0;
        $clave = 'sol';

        foreach ($xp->query('measure', $parte) as $medida) {
            foreach ($xp->query('attributes', $medida) as $attr) {
                $b = $xp->evaluate('string(time/beats)', $attr);
                $t = $xp->evaluate('string(time/beat-type)', $attr);
                if ($b !== '' && $t !== '') {
                    $compas = $b.'/'.$t;
                    $actual['compas'] = $actual['notas'] ? $actual['compas'] : $compas;
                }
                $f = $xp->evaluate('string(key/fifths)', $attr);
                if ($f !== '') {
                    $fifths = (int) $f;
                    if (! $actual['notas']) {
                        $actual['fifths'] = $fifths;
                    }
                }
                $signo = $xp->evaluate('string(clef/sign)', $attr);
                if ($signo !== '') {
                    $clave = ['G' => 'sol', 'C' => 'do', 'F' => 'fa'][$signo] ?? 'sol';
                    if (! $actual['notas']) {
                        $actual['clave'] = $clave;
                    }
                }
            }

            foreach ($xp->query('note', $medida) as $nota) {
                if ($xp->query('grace', $nota)->length || $xp->query('chord', $nota)->length) {
                    continue; // adornos y notas de acorde (se toma la superior: la primera)
                }
                $voz = $xp->evaluate('string(voice)', $nota);
                if ($voz !== '' && $voz !== '1') {
                    continue;
                }
                $tipo = self::FIGURA[$xp->evaluate('string(type)', $nota)] ?? 'negra';
                if ($xp->query('dot', $nota)->length && (self::PUNTILLO[$tipo] ?? null)) {
                    $tipo = self::PUNTILLO[$tipo];
                }
                if ($xp->query('rest', $nota)->length) {
                    $actual['notas'][] = ['silencio' => true, 'figura' => $tipo];

                    continue;
                }
                $paso = $xp->evaluate('string(pitch/step)', $nota);
                if ($paso === '') {
                    continue;
                }
                $dedo = $xp->evaluate('string(notations/technical/fingering)', $nota);
                $actual['notas'][] = [
                    'letra' => $paso,
                    'alteracion' => (int) round((float) $xp->evaluate('string(pitch/alter)', $nota)),
                    'octava' => (int) $xp->evaluate('string(pitch/octave)', $nota),
                    'figura' => $tipo,
                    'dedo' => ctype_digit($dedo) && (int) $dedo <= 4 ? (int) $dedo : null,
                ];
            }
            $actual['compases']++;

            $barra = $xp->evaluate('string(barline[@location="right"]/bar-style)', $medida);
            if (in_array($barra, ['light-light', 'light-heavy', 'heavy-light', 'heavy-heavy'], true) && $this->tieneNotas($actual)) {
                $ejercicios[] = $actual;
                $actual = $this->nuevo(count($ejercicios) + 1, $compas, $fifths, $clave);
            }
        }

        if ($this->tieneNotas($actual)) {
            $ejercicios[] = $actual;
        }

        return $ejercicios;
    }

    /** Lee un .xml o un .mxl (MusicXML comprimido). */
    public function leerArchivo(string $ruta): ?string
    {
        if (str_ends_with(strtolower($ruta), '.mxl')) {
            if (! class_exists(\ZipArchive::class)) {
                return null;
            }
            $zip = new \ZipArchive;
            if ($zip->open($ruta) !== true) {
                return null;
            }
            $contenido = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nombre = $zip->getNameIndex($i);
                if (str_ends_with($nombre, '.xml') && ! str_starts_with($nombre, 'META-INF')) {
                    $contenido = $zip->getFromIndex($i);
                    break;
                }
            }
            $zip->close();

            return $contenido ?: null;
        }

        return is_file($ruta) ? (string) file_get_contents($ruta) : null;
    }

    private function nuevo(int $numero, string $compas, int $fifths, string $clave): array
    {
        return ['numero' => $numero, 'compas' => $compas, 'fifths' => $fifths, 'clave' => $clave, 'notas' => [], 'compases' => 0];
    }

    private function tieneNotas(array $e): bool
    {
        foreach ($e['notas'] as $n) {
            if (empty($n['silencio'])) {
                return true;
            }
        }

        return false;
    }
}
