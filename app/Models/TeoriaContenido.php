<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeoriaContenido extends Model
{
    protected $table = 'teoria_contenidos';

    protected $primaryKey = 'id_teoria';

    protected $fillable = ['id_leccion', 'titulo', 'pregunta', 'opciones', 'respuesta_correcta', 'nivel', 'xp', 'activo'];

    protected $hidden = ['respuesta_correcta'];

    protected function casts(): array
    {
        return ['opciones' => 'array', 'activo' => 'boolean'];
    }
}
