<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaCorreo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreProfesorRequest extends FormRequest
{
    use NormalizaCorreo;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombres' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:100', 'unique:usuarios,correo'],
            'contrasena' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'ci' => ['nullable', 'string', 'max:20'],
            'celular' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'especialidad' => ['nullable', 'string', 'max:50'],
        ];
    }
}
