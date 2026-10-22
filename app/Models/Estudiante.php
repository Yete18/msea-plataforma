<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    protected $table = 'estudiantes';

    protected $primaryKey = 'id_estudiante';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'fecha_ingreso',
        'id_elenco',
        'id_profesor',
        'id_seccion',
        'monto_pago',
        'meta_diaria_minutos',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'monto_pago' => 'decimal:2',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function seccion()
    {
        return $this->belongsTo(Seccion::class, 'id_seccion', 'id_seccion');
    }

    public function elenco()
    {
        return $this->belongsTo(Elenco::class, 'id_elenco', 'id_elenco');
    }

    public function profesor()
    {
        return $this->belongsTo(Profesor::class, 'id_profesor', 'id_profesor');
    }

    public function tareas()
    {
        return $this->hasMany(Tarea::class, 'id_estudiante', 'id_estudiante');
    }

    public function progresos()
    {
        return $this->hasMany(Progreso::class, 'id_estudiante', 'id_estudiante');
    }

    public function practicas()
    {
        return $this->hasMany(Practica::class, 'id_estudiante', 'id_estudiante');
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class, 'id_estudiante', 'id_estudiante');
    }

    public function recompensas()
    {
        return $this->belongsToMany(Recompensa::class, 'estudiante_recompensa', 'id_estudiante', 'id_recompensa')
            ->withPivot(['id', 'fecha']);
    }

    public function cursos()
    {
        return $this->belongsToMany(Curso::class, 'curso_estudiante', 'id_estudiante', 'id_curso')
            ->withPivot(['id', 'inscrito_at']);
    }

    public function gamificacion()
    {
        return $this->hasOne(GamificacionPerfil::class, 'id_estudiante', 'id_estudiante');
    }

    public function logros()
    {
        return $this->belongsToMany(Logro::class, 'estudiante_logro', 'id_estudiante', 'id_logro')
            ->withPivot(['id', 'desbloqueado_at']);
    }

    public function padres()
    {
        return $this->belongsToMany(Padre::class, 'estudiante_padre', 'id_estudiante', 'id_padre')
            ->withPivot('id');
    }
}
