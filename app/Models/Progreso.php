<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Progreso extends Model
{
    protected $table = 'progreso';

    protected $primaryKey = 'id_progreso';

    public $timestamps = false;

    protected $fillable = [
        'id_estudiante',
        'id_ejercicio',
        'precision',
        'puntaje',
        'estado',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'precision' => 'decimal:2',
            'puntaje' => 'decimal:2',
            'fecha' => 'datetime',
        ];
    }

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public function ejercicio()
    {
        return $this->belongsTo(Ejercicio::class, 'id_ejercicio', 'id_ejercicio');
    }
}
