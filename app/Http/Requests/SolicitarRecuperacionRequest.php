<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SolicitarRecuperacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Escribe tu correo institucional.',
            'email.email' => 'Escribe un correo electrónico válido.',
            'email.max' => 'El correo es demasiado largo.',
        ];
    }
}