<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seccion extends Model
{
    protected $table = 'secciones';

    protected $primaryKey = 'id_seccion';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function estudiantes()
    {
        return $this->hasMany(Estudiante::class, 'id_seccion', 'id_seccion');
    }
}
