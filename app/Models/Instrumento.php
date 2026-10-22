<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Instrumento extends Model
{
    protected $table = 'instrumentos';

    protected $primaryKey = 'id_instrumento';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function usuarios()
    {
        return $this->belongsToMany(Usuario::class, 'usuario_instrumento', 'id_instrumento', 'id_usuario')
            ->withPivot('id');
    }
}
