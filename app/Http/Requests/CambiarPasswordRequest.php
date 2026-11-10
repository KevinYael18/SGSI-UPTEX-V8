<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Cambio de contraseña del usuario autenticado (solo la suya: no existe
 * parámetro de usuario en la ruta ni en el cuerpo).
 * Requisitos: mínimo 12 caracteres (misma longitud que el resto del sistema)
 * con mayúsculas, minúsculas y números.
 */
class CambiarPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'password_actual' => ['required', 'string'],
            'password' => [
                'required', 'string', 'confirmed', 'max:255', 'different:password_actual',
                Password::min(12)->letters()->mixedCase()->numbers(),
            ],
        ];
    }

    /**
     * La contraseña actual se verifica a mano contra `password_hash`
     * (la regla `current_password` de Laravel intentaría re-hashear sobre
     * una columna `password` que esta tabla no tiene).
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $actual = (string) $this->input('password_actual', '');
                if ($actual !== '' && !Hash::check($actual, $this->user()->getAuthPassword())) {
                    $validator->errors()->add('password_actual', 'La contraseña actual no es correcta.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'password_actual.required' => 'Escribe tu contraseña actual.',
            'password.required' => 'Escribe la nueva contraseña.',
            'password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
            'password.min' => 'La nueva contraseña debe tener al menos 12 caracteres.',
            // La regla Password registra sus fallas como "password.<regla>".
            'password.password.letters' => 'La nueva contraseña debe incluir letras.',
            'password.password.mixed' => 'La nueva contraseña debe incluir mayúsculas y minúsculas.',
            'password.password.numbers' => 'La nueva contraseña debe incluir al menos un número.',
        ];
    }
}
