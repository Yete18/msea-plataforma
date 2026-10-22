<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaCorreo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    use NormalizaCorreo;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'correo' => 'required|email',
            'contrasena' => 'required',
            // UX-07: el rol sale de la cuenta. Se acepta (y se ignora) por compatibilidad con formularios viejos.
            'rol' => ['nullable', Rule::in(['estudiante', 'profesor', 'director', 'admin', 'padre'])],
        ];
    }
}
