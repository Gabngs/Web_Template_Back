<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CambiarPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Ambos van RSA+base64, igual que "password" en el login —
            // la validación de longitud/complejidad ocurre tras desencriptar.
            'password_actual' => 'required|string',
            'password_nueva'  => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'password_actual.required' => 'La contraseña actual es requerida.',
            'password_nueva.required'  => 'La nueva contraseña es requerida.',
        ];
    }
}
