<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Leccion extends Model
{
    protected $table = 'lecciones';

    protected $primaryKey = 'id_leccion';

    protected $fillable = ['id_modulo', 'titulo', 'contenido', 'tipo', 'orden', 'xp', 'publicado'];

    protected function casts(): array
    {
        return ['publicado' => 'boolean'];
    }

    public function modulo()
    {
        return $this->belongsTo(Modulo::class, 'id_modulo', 'id_modulo');
    }

    public function teoria()
    {
        return $this->hasMany(TeoriaContenido::class, 'id_leccion', 'id_leccion');
    }
}
