<?php

namespace App\Http\Requests\Concerns;

use App\Services\AuthService;

/**
 * Pasa el campo "correo" a minusculas y sin espacios antes de validar, para que
 * "Juan@Msea.test" y "juan@msea.test" sean siempre la misma cuenta.
 */
trait NormalizaCorreo
{
    protected function prepareForValidation(): void
    {
        if ($this->has('correo') && is_string($this->input('correo'))) {
            $this->merge(['correo' => AuthService::normalizarCorreo($this->input('correo'))]);
        }
    }
}
