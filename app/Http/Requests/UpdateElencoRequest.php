<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateElencoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', function (string $attr, mixed $valor, \Closure $fail) {
                $repetido = DB::table('elencos')
                    ->whereRaw('lower(nombre) = ?', [mb_strtolower(trim((string) $valor))])->where('id_elenco', '<>', (int) $this->route('idElenco'))
                    ->exists();
                if ($repetido) {
                    $fail('Ya existe un elenco con ese nombre.');
                }
            }],
            'tipo' => ['required', Rule::in(['Orquesta', 'Elenco'])],
        ];
    }
}
