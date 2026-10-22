<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeccionProgreso extends Model
{
    protected $table = 'leccion_progreso';

    protected $fillable = ['id_leccion', 'id_estudiante', 'estado', 'completado_at'];

    protected function casts(): array
    {
        return ['completado_at' => 'datetime'];
    }
}
