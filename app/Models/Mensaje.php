<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mensaje extends Model
{
    protected $table = 'mensajes';

    protected $primaryKey = 'id_mensaje';

    protected $fillable = ['id_remitente', 'id_destinatario', 'asunto', 'contenido', 'leido_at'];

    protected function casts(): array
    {
        return ['leido_at' => 'datetime'];
    }
}
