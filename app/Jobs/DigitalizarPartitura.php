<?php

namespace App\Jobs;

use App\Services\DigitalizadorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Reconoce las páginas elegidas de un método en PDF (con MSEA_OMR_EN_COLA=true,
 * que es lo predeterminado en producción). Va a la conexión "database-largo",
 * cuyo retry_after supera el timeout, y es único por digitalización: subir o
 * reprocesar dos veces no lo ejecuta en paralelo.
 */
class DigitalizarPartitura implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    /** Segundos que dura el candado de unicidad. */
    public int $uniqueFor = 3900;

    public function __construct(public readonly int $idDigitalizacion)
    {
        $this->onConnection('database-largo');
        $this->onQueue('omr');
    }

    public function uniqueId(): string
    {
        return (string) $this->idDigitalizacion;
    }

    public function handle(DigitalizadorService $digitalizador): void
    {
        $digitalizador->procesar($this->idDigitalizacion);
    }
}
