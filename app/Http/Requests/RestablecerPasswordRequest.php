<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Nueva contraseña desde el enlace de recuperación. Mismos requisitos que el
 * cambio de contraseña de «Mi perfil» (CambiarPasswordRequest): mínimo 12
 * caracteres, con mayúsculas, minúsculas y números.
 */
class RestablecerPasswordRequest extends FormRequest
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
            'password' => [
                'required', 'string', 'confirmed', 'max:255',
                Password::min(12)->letters()->mixedCase()->numbers(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'El enlace de recuperación está incompleto. Ábrelo de nuevo desde el correo.',
            'email.required' => 'El enlace de recuperación está incompleto. Ábrelo de nuevo desde el correo.',
            'email.email' => 'El enlace de recuperación no es válido.',
            'password.required' => 'Escribe la nueva contraseña.',
            'password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
            'password.max' => 'La contraseña no puede superar 255 caracteres.',
            'password.min' => 'La nueva contraseña debe tener al menos 12 caracteres.',
            'password.password.letters' => 'La nueva contraseña debe incluir letras.',
            'password.password.mixed' => 'La nueva contraseña debe incluir mayúsculas y minúsculas.',
            'password.password.numbers' => 'La nueva contraseña debe incluir al menos un número.',
        ];
    }
}
