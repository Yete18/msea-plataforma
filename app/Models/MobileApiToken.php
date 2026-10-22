<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileApiToken extends Model
{
    protected $table = 'mobile_api_tokens';

    protected $fillable = [
        'id_usuario',
        'token_hash',
        'device_name',
        'last_used_at',
        'expires_at',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
