<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignStudentToElencoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_estudiante' => ['required', 'integer', 'exists:estudiantes,id_estudiante'],
            'id_elenco' => ['nullable', 'integer', 'exists:elencos,id_elenco'],
        ];
    }
}
