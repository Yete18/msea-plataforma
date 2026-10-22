<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaCorreo;
use App\Services\AuthService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    use NormalizaCorreo;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'correo' => 'required|email|unique:usuarios,correo',
            'contrasena' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'nombres' => 'required|string|max:100',
            'apellido_paterno' => 'required|string|max:100',
            'apellido_materno' => 'nullable|string|max:100',
            // Solo instrumentos del catalogo: el registro publico nunca crea instrumentos.
            'instrumento' => ['nullable', 'string', 'max:50', function (string $attr, mixed $valor, \Closure $fail) {
                if ($valor !== null && $valor !== '' && ! AuthService::idInstrumentoExistente((string) $valor)) {
                    $fail('Elige uno de los instrumentos de la lista.');
                }
            }],
            'nivel' => 'nullable|string|max:50|exists:secciones,nombre',
            'rol' => ['required', Rule::in(['estudiante'])],
            'acepta_privacidad' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'acepta_privacidad.accepted' => 'Debes aceptar la política de privacidad para crear tu cuenta.',
        ];
    }
}
