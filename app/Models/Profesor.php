<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profesor extends Model
{
    protected $table = 'profesores';

    protected $primaryKey = 'id_profesor';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function estudiantes()
    {
        return $this->hasMany(Estudiante::class, 'id_profesor', 'id_profesor');
    }

    public function tareas()
    {
        return $this->hasMany(Tarea::class, 'id_profesor', 'id_profesor');
    }

    public function ejerciciosCreados()
    {
        return $this->hasMany(Ejercicio::class, 'creado_por', 'id_profesor');
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class, 'id_profesor', 'id_profesor');
    }

    public function cursos()
    {
        return $this->hasMany(Curso::class, 'id_profesor', 'id_profesor');
    }
}
