<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoEjercicio extends Model
{
    protected $table = 'tipos_ejercicio';

    protected $primaryKey = 'id_tipo';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function ejercicios()
    {
        return $this->hasMany(Ejercicio::class, 'id_tipo', 'id_tipo');
    }
}
