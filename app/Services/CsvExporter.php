<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta CSV seguro para Excel/LibreOffice/Google Sheets:
 * - celdas que empiezan con = + - @ (o tab / retorno) se anteponen con un
 *   apostrofo para que la hoja NO las ejecute como formula (S8);
 * - comas, comillas y saltos de linea los maneja fputcsv;
 * - BOM UTF-8 para que Excel muestre bien las tildes.
 * Los datos originales de la base de datos no se modifican.
 */
class CsvExporter
{
    public static function neutralizar(mixed $valor): string
    {
        $texto = (string) ($valor ?? '');

        if ($texto !== '' && in_array($texto[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            // Numeros negativos legitimos se dejan tal cual.
            if (! preg_match('/^-\d+([.,]\d+)?$/', $texto)) {
                return "'".$texto;
            }
        }

        return $texto;
    }

    public function descargar(string $archivo, array $encabezados, iterable $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($encabezados, $filas) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");

            if ($encabezados) {
                fputcsv($salida, array_map([self::class, 'neutralizar'], $encabezados));
            }

            foreach ($filas as $fila) {
                fputcsv($salida, array_map([self::class, 'neutralizar'], (array) $fila));
            }

            fclose($salida);
        }, $archivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
