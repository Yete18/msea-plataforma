<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaCorreo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDirectorProfileRequest extends FormRequest
{
    use NormalizaCorreo;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $idUsuario = (int) session('usuario_id');

        return [
            'nombres' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['nullable', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:100', Rule::unique('usuarios', 'correo')->ignore($idUsuario, 'id_usuario')],
            // Obligatoria solo si el correo cambia (se verifica en el servicio de perfil).
            'contrasena_actual' => ['nullable', 'string', 'max:255'],
            'ci' => ['nullable', 'string', 'max:20'],
            'celular' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'trayectoria' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
