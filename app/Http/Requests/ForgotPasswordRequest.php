<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaCorreo;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
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
        ];
    }
}
