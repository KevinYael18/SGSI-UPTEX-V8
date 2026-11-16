<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IaChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('ia.usar');
    }

    public function rules(): array
    {
        return [
            'mensaje' => ['required', 'string', 'min:1', 'max:' . (int) config('sgsi.ia.limites.max_caracteres_mensaje')],
            'conversacion_uuid' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'mensaje.required' => 'Escriba su pregunta.',
            'mensaje.max' => 'La pregunta no puede exceder :max caracteres.',
        ];
    }
}
