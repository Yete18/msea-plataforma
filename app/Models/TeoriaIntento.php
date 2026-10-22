<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeoriaIntento extends Model
{
    protected $table = 'teoria_intentos';

    public $timestamps = false;

    protected $fillable = ['id_teoria', 'id_estudiante', 'respuesta', 'correcta', 'created_at'];

    protected function casts(): array
    {
        return ['correcta' => 'boolean', 'created_at' => 'datetime'];
    }
}
