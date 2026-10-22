<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ejercicio extends Model
{
    protected $table = 'ejercicios';

    protected $primaryKey = 'id_ejercicio';

    public $timestamps = false;

    protected $fillable = [
        'id_tipo',
        'titulo',
        'descripcion',
        'archivo',
        'creado_por',
        'dificultad',
        'xp',
    ];

    public function tipo()
    {
        return $this->belongsTo(TipoEjercicio::class, 'id_tipo', 'id_tipo');
    }

    public function creador()
    {
        return $this->belongsTo(Profesor::class, 'creado_por', 'id_profesor');
    }

    public function tareas()
    {
        return $this->belongsToMany(Tarea::class, 'tarea_ejercicio', 'id_ejercicio', 'id_tarea')
            ->withPivot('id');
    }

    public function progresos()
    {
        return $this->hasMany(Progreso::class, 'id_ejercicio', 'id_ejercicio');
    }

    public function practicas()
    {
        return $this->hasMany(Practica::class, 'id_ejercicio', 'id_ejercicio');
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class, 'id_ejercicio', 'id_ejercicio');
    }
}
