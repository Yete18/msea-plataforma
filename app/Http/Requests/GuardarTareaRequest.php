<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creando = $this->isMethod('post') && ! $this->route('idTarea');

        return [
            'titulo' => ['required_without:id_biblioteca', 'nullable', 'string', 'max:150'],
            // Tarea de escala: se elige de la biblioteca y se indica cómo tocarla (JSON).
            'id_biblioteca' => ['nullable', 'integer', 'min:1'],
            'config_escala' => ['nullable', 'required_with:id_biblioteca'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'id_estudiante' => [$creando ? 'required_without:id_elenco' : 'nullable', 'nullable', 'integer', 'exists:estudiantes,id_estudiante'],
            'id_elenco' => [$creando ? 'required_without:id_estudiante' : 'nullable', 'nullable', 'integer', 'exists:elencos,id_elenco'],
            'fecha_limite' => ['nullable', 'date', $creando ? 'after_or_equal:today' : 'nullable'],
            'xp_recompensa' => ['nullable', 'integer', 'min:5', 'max:200'],
            'tipo' => ['nullable', 'string', 'exists:tipos_ejercicio,nombre'],
            'material' => [
                'nullable', 'file',
                'max:'.(int) config('msea.archivos.material_max_kb'),
                'mimes:'.implode(',', (array) config('msea.archivos.material_mimes')),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_estudiante' => 'estudiante',
            'id_elenco' => 'elenco',
            'fecha_limite' => 'fecha limite',
            'xp_recompensa' => 'XP de recompensa',
            'id_biblioteca' => 'escala',
            'config_escala' => 'configuración de la escala',
        ];
    }
}
