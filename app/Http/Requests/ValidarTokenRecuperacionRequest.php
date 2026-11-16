<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Comprobación previa (sin consumirlo) de un enlace de recuperación, para que
 * la pantalla de nueva contraseña avise de inmediato si el enlace ya expiró
 * o ya se utilizó, en lugar de dejar que el usuario escriba su contraseña en vano.
 */
class ValidarTokenRecuperacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'El enlace de recuperación está incompleto.',
            'email.required' => 'El enlace de recuperación está incompleto.',
            'email.email' => 'El enlace de recuperación no es válido.',
        ];
    }
}
