<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    protected $table = 'auditoria';

    public $timestamps = false;

    protected $fillable = ['id_usuario', 'accion', 'detalle', 'ip', 'user_agent', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'detalle' => 'array'];
    }
}
