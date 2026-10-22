<?php

namespace App\Support;

use RuntimeException;

/**
 * Protege los down() de migraciones que borran datos (historial de XP, entregas,
 * bitacora, escalas, digitalizaciones...). En produccion la forma de volver atras
 * es restaurar el respaldo (ver DEPLOYMENT.md), no `migrate:rollback`.
 *
 * Para revertir de todas formas (por ejemplo en un servidor de pruebas con datos
 * copiados) hay que poner MSEA_PERMITIR_ROLLBACK=true de forma explicita.
 */
class ReversionSegura
{
    public static function verificar(string $migracion): void
    {
        if (! app()->environment('production')) {
            return;
        }

        if (filter_var(env('MSEA_PERMITIR_ROLLBACK', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        throw new RuntimeException(
            "La reversion de {$migracion} borra datos reales y esta bloqueada en produccion. "
            .'Restaura el respaldo de la base (DEPLOYMENT.md, sección Respaldo y restauración). '
            .'Si de verdad necesitas revertir, define MSEA_PERMITIR_ROLLBACK=true solo durante esa operación.'
        );
    }
}
