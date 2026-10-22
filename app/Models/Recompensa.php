<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recompensa extends Model
{
    protected $table = 'recompensas';

    protected $primaryKey = 'id_recompensa';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function estudiantes()
    {
        return $this->belongsToMany(Estudiante::class, 'estudiante_recompensa', 'id_recompensa', 'id_estudiante')
            ->withPivot(['id', 'fecha']);
    }
}
