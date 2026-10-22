<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * Notación de texto para revisar y corregir lo que reconoce el digitalizador.
 *
 *   Si4:c  Do#5:c  Re5:n.  -:c  Mi5:s/2  |
 *
 *   nota + octava : Do Re Mi Fa Sol La Si, con # o b (también ♯ ♭), y la octava (La4 = 440 Hz)
 *   figura        : r redonda, b blanca, n negra, c corchea, s semicorchea; "." = puntillo
 *   -             : silencio
 *   /2            : dedo (opcional, 0 a 4)
 *   |             : barra de compás (solo para leer mejor; se ignora)
 */
class NotacionTexto
{
    private const SOLFEO = ['do' => 'C', 're' => 'D', 'mi' => 'E', 'fa' => 'F', 'sol' => 'G', 'la' => 'A', 'si' => 'B'];

    private const LETRA_A_SOLFEO = ['C' => 'Do', 'D' => 'Re', 'E' => 'Mi', 'F' => 'Fa', 'G' => 'Sol', 'A' => 'La', 'B' => 'Si'];

    private const FIGURA = ['r' => 'redonda', 'b' => 'blanca', 'n' => 'negra', 'c' => 'corchea', 's' => 'semicorchea'];

    private const PUNTILLO = ['blanca' => 'blanca_puntillo', 'negra' => 'negra_puntillo', 'corchea' => 'corchea_puntillo'];

    public static function aTexto(array $notas): string
    {
        $codigo = array_flip(self::FIGURA) + ['blanca_puntillo' => 'b.', 'negra_puntillo' => 'n.', 'corchea_puntillo' => 'c.'];
        $partes = [];
        foreach ($notas as $n) {
            $fig = $codigo[$n['figura'] ?? 'negra'] ?? 'n';
            if (! empty($n['silencio'])) {
                $partes[] = '-:'.$fig;

                continue;
            }
            $alter = (int) ($n['alteracion'] ?? 0);
            $signo = $alter > 0 ? str_repeat('#', $alter) : str_repeat('b', -$alter);
            $dedo = isset($n['dedo']) && $n['dedo'] !== null && $n['dedo'] !== '' ? '/'.(int) $n['dedo'] : '';
            $partes[] = (self::LETRA_A_SOLFEO[$n['letra']] ?? $n['letra']).$signo.$n['octava'].':'.$fig.$dedo;
        }

        return implode(' ', $partes);
    }

    /** @throws ValidationException con la posición del primer error */
    public static function aNotas(string $texto, string $campo = 'notas'): array
    {
        $texto = str_replace(['♯', '♭', ',', ';', "\r"], ['#', 'b', ' ', ' ', ' '], $texto);
        $tokens = preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $notas = [];

        foreach ($tokens as $i => $token) {
            if ($token === '|' || $token === '||') {
                continue;
            }
            if (! preg_match('/^(?:(?<nota>do|re|mi|fa|sol|la|si)(?<alt>##|bb|#|b)?(?<oct>[0-8])|(?<sil>-)):(?<fig>[rbncs])(?<dot>\.)?(?:\/(?<dedo>[0-4]))?$/iu', $token, $m)) {
                throw ValidationException::withMessages([$campo => "No entiendo «{$token}» (posición ".($i + 1).'). Usa, por ejemplo, Do#5:c o -:n.']);
            }
            $figura = self::FIGURA[strtolower($m['fig'])];
            if (! empty($m['dot'])) {
                if (! isset(self::PUNTILLO[$figura])) {
                    throw ValidationException::withMessages([$campo => "«{$token}»: esa figura no admite puntillo aquí."]);
                }
                $figura = self::PUNTILLO[$figura];
            }
            if (! empty($m['sil'])) {
                $notas[] = ['silencio' => true, 'figura' => $figura];

                continue;
            }
            $alt = $m['alt'] ?? '';
            $notas[] = [
                'letra' => self::SOLFEO[mb_strtolower($m['nota'])],
                'alteracion' => match ($alt) {
                    '#' => 1, '##' => 2, 'b' => -1, 'bb' => -2, default => 0
                },
                'octava' => (int) $m['oct'],
                'figura' => $figura,
                'dedo' => isset($m['dedo']) && $m['dedo'] !== '' ? (int) $m['dedo'] : null,
            ];
        }

        if (count($notas) > 600) {
            throw ValidationException::withMessages([$campo => 'Un ejercicio admite hasta 600 notas.']);
        }
        if (! array_filter($notas, fn ($n) => empty($n['silencio']))) {
            throw ValidationException::withMessages([$campo => 'El ejercicio no tiene notas.']);
        }

        return $notas;
    }
}
