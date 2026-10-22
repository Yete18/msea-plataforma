<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CambiarContrasenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contrasena_actual' => ['required', 'string', 'max:255'],
            'contrasena' => ['required', 'confirmed', 'different:contrasena_actual', Password::min(8)->letters()->numbers()],
        ];
    }
}
