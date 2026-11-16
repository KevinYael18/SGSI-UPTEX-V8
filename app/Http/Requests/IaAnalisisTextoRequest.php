<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Texto opcional que acompaña un análisis (notas del auditor o pregunta sobre un documento). */
class IaAnalisisTextoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('ia.usar');
    }

    public function rules(): array
    {
        return [
            'notas' => ['nullable', 'string', 'max:4000'],
            'pregunta' => ['nullable', 'string', 'max:500'],
        ];
    }
}
