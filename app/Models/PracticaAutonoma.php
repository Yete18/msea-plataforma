<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sesion de practica. La duracion la mide el servidor (iniciada_at -> finalizada_at);
 * las metricas que envia el navegador se guardan como NO verificadas.
 */
class PracticaAutonoma extends Model
{
    protected $table = 'practicas_autonomas';

    protected $primaryKey = 'id_practica_autonoma';

    protected $fillable = [
        'id_estudiante', 'tipo', 'duracion_segundos', 'xp_ganado', 'notas', 'estado',
        'id_ejercicio', 'iniciada_at', 'finalizada_at', 'metricas', 'metricas_verificadas',
    ];

    protected function casts(): array
    {
        return [
            'iniciada_at' => 'datetime',
            'finalizada_at' => 'datetime',
            'metricas' => 'array',
            'metricas_verificadas' => 'boolean',
        ];
    }
}
