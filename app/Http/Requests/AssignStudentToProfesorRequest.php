<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignStudentToProfesorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_estudiante' => ['required', 'integer', 'exists:estudiantes,id_estudiante'],
            'id_profesor' => ['nullable', 'integer', 'exists:profesores,id_profesor'],
        ];
    }
}
