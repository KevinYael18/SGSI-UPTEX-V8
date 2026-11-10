<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRiesgoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('riesgo'));
    }

    public function rules(): array
    {
        return [
            'activo_id' => ['sometimes', 'exists:activos,id'],
            'amenaza_id' => ['sometimes', 'exists:amenazas,id'],
            'vulnerabilidad_id' => ['sometimes', 'exists:vulnerabilidades,id'],
            'probabilidad' => ['sometimes', 'integer', 'between:1,5'],
            'impacto' => ['sometimes', 'integer', 'between:1,5'],
            'tipo_tratamiento' => ['sometimes', 'nullable', 'in:mitigar,transferir,evitar,aceptar'],
        ];
    }
}