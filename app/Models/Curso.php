<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Curso extends Model
{
    protected $table = 'cursos';

    protected $primaryKey = 'id_curso';

    protected $fillable = [
        'titulo',
        'descripcion',
        'instrumento',
        'nivel',
        'id_profesor',
        'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function profesor()
    {
        return $this->belongsTo(Profesor::class, 'id_profesor', 'id_profesor');
    }

    public function modulos()
    {
        return $this->hasMany(Modulo::class, 'id_curso', 'id_curso')->orderBy('orden');
    }

    public function estudiantes()
    {
        return $this->belongsToMany(Estudiante::class, 'curso_estudiante', 'id_curso', 'id_estudiante')
            ->withPivot('id', 'inscrito_at');
    }
}
