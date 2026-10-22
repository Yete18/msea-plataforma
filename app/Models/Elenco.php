<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Elenco extends Model
{
    protected $table = 'elencos';

    protected $primaryKey = 'id_elenco';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'id_tipo',
    ];

    public function tipo()
    {
        return $this->belongsTo(TipoElenco::class, 'id_tipo', 'id_tipo');
    }

    public function estudiantes()
    {
        return $this->hasMany(Estudiante::class, 'id_elenco', 'id_elenco');
    }

    public function tareas()
    {
        return $this->hasMany(Tarea::class, 'id_elenco', 'id_elenco');
    }
}
