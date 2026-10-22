<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GamificacionPerfil extends Model
{
    protected $table = 'gamificacion_perfiles';

    protected $primaryKey = 'id_gamificacion';

    protected $fillable = ['id_estudiante', 'xp_total', 'nivel', 'racha_actual', 'mejor_racha', 'ultima_practica'];

    protected function casts(): array
    {
        return ['ultima_practica' => 'date'];
    }
}
