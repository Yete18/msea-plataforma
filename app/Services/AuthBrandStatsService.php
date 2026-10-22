<?php

namespace App\Services;

use App\Models\Estudiante;
use App\Models\Instrumento;
use App\Models\Profesor;
use App\Models\Seccion;
use Illuminate\Support\Facades\Schema;

class AuthBrandStatsService
{
    /**
     * Returns the public counts displayed on authentication screens.
     */
    public function metrics(): array
    {
        if (! Schema::hasTable('estudiantes')) {
            return $this->emptyMetrics();
        }

        $activeStudents = Estudiante::query();

        if (Schema::hasColumn('usuarios', 'ultimo_ingreso_at')) {
            $activeStudents->whereHas('usuario', fn ($query) => $query
                ->where('ultimo_ingreso_at', '>=', now()->subDays(30)));
        }

        return [
            ['value' => $activeStudents->count(), 'label' => 'Estudiantes activos'],
            ['value' => Profesor::query()->count(), 'label' => 'Docentes'],
            ['value' => Instrumento::query()->count(), 'label' => 'Instrumentos'],
            ['value' => Seccion::query()->count(), 'label' => 'Niveles'],
        ];
    }

    private function emptyMetrics(): array
    {
        return [
            ['value' => 0, 'label' => 'Estudiantes activos'],
            ['value' => 0, 'label' => 'Docentes'],
            ['value' => 0, 'label' => 'Instrumentos'],
            ['value' => 0, 'label' => 'Niveles'],
        ];
    }
}
