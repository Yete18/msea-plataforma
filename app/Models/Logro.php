<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Logro extends Model
{
    protected $table = 'logros';

    protected $primaryKey = 'id_logro';

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'xp_bonus', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
