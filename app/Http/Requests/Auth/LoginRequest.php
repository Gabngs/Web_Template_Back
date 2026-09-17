<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Acepta email o "codigo" (5 dígitos autogenerado por pkid, ej: 00001)
            'email'    => 'required|string|max:150',
            'password' => 'required|string',
            'nonce'    => 'required|uuid',
            'device'   => 'sometimes|string|in:web,mobile,tablet',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => 'El email o código es requerido.',
            'password.required' => 'La contraseña es requerida.',
            'nonce.required'    => 'Challenge requerido. Llame primero a GET /auth/challenge.',
            'nonce.uuid'        => 'Formato de challenge inválido.',
            'device.in'         => 'Dispositivo inválido. Use: web, mobile, tablet.',
        ];
    }
}
