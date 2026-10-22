<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetCode extends Model
{
    protected $table = 'password_reset_codes';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'correo',
        'codigo',
        'expires_at',
        'used_at',
        'created_at',
        'intentos',
    ];

    protected $hidden = [
        'codigo',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
