<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoElenco extends Model
{
    protected $table = 'tipos_elencos';

    protected $primaryKey = 'id_tipo';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function elencos()
    {
        return $this->hasMany(Elenco::class, 'id_tipo', 'id_tipo');
    }
}
