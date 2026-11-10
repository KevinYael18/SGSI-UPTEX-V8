<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRiesgoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Riesgo::class);
    }

    public function rules(): array
    {
        return [
            'activo_id' => ['required', 'exists:activos,id'],
            'amenaza_id' => ['required', 'exists:amenazas,id'],
            'vulnerabilidad_id' => ['required', 'exists:vulnerabilidades,id'],
            'probabilidad' => ['required', 'integer', 'between:1,5'],
            'impacto' => ['nullable', 'integer', 'between:1,5'],
            'tipo_tratamiento' => ['nullable', 'in:mitigar,transferir,evitar,aceptar'],
            'plan_tratamiento' => ['sometimes', 'array'],
            'plan_tratamiento.descripcion' => ['required_with:plan_tratamiento', 'string'],
            'plan_tratamiento.responsable_id' => ['nullable', 'exists:usuarios,id'],
            'plan_tratamiento.fecha_objetivo' => ['nullable', 'date', 'after:today'],
        ];
    }
}