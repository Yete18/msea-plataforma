<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    protected $table = 'modulos';

    protected $primaryKey = 'id_modulo';

    protected $fillable = ['id_curso', 'titulo', 'descripcion', 'orden', 'publicado'];

    protected function casts(): array
    {
        return ['publicado' => 'boolean'];
    }

    public function curso()
    {
        return $this->belongsTo(Curso::class, 'id_curso', 'id_curso');
    }

    public function lecciones()
    {
        return $this->hasMany(Leccion::class, 'id_modulo', 'id_modulo')->orderBy('orden');
    }
}
