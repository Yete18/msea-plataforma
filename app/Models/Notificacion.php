<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $primaryKey = 'id_notificacion';

    protected $fillable = ['id_usuario', 'titulo', 'mensaje', 'tipo', 'leida_at'];

    protected function casts(): array
    {
        return ['leida_at' => 'datetime'];
    }
}
