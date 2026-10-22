<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaCorreo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStudentProfileRequest extends FormRequest
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
            'correo' => [
                'required',
                'email',
                'max:100',
                Rule::unique('usuarios', 'correo')->ignore($idUsuario, 'id_usuario'),
            ],
            // Obligatoria solo si el correo cambia (se verifica en el servicio de perfil).
            'contrasena_actual' => ['nullable', 'string', 'max:255'],
            // UX-09: CI, dirección y fecha de nacimiento son datos oficiales del
            // menor: los corrige la dirección, no el estudiante (se ignoran aquí).
            'celular' => ['nullable', 'string', 'max:20'],
            'foto' => ['nullable', 'string', 'max:3000000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $foto = $this->input('foto');

            if (! empty($foto) && ! preg_match('/^data:image\/(png|jpe?g|webp);base64,/', $foto)) {
                $validator->errors()->add('foto', 'La foto debe ser una imagen válida.');
            }
        });
    }
}
