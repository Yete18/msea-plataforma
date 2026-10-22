<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioInstrumento extends Model
{
    protected $table = 'usuario_instrumento';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'id_instrumento',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function instrumento()
    {
        return $this->belongsTo(Instrumento::class, 'id_instrumento', 'id_instrumento');
    }
}
