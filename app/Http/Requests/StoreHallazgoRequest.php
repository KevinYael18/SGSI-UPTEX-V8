<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHallazgoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('auditoria'));
    }

    public function rules(): array
    {
        return [
            'severidad' => ['required', 'in:menor,mayor,critica'],
            'descripcion' => ['required', 'string', 'max:5000'],
            'no_conformidad' => ['sometimes', 'array'],
            'no_conformidad.accion_correctiva' => ['sometimes', 'array'],
            'no_conformidad.accion_correctiva.responsable_id' => ['required_with:no_conformidad.accion_correctiva', 'exists:usuarios,id'],
            'no_conformidad.accion_correctiva.fecha_compromiso' => ['required_with:no_conformidad.accion_correctiva', 'date', 'after:today'],
        ];
    }
}