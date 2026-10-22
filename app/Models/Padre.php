<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Padre extends Model
{
    protected $table = 'padres';

    protected $primaryKey = 'id_padre';

    protected $fillable = ['id_usuario', 'parentesco'];
}
