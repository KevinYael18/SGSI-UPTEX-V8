<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateActivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('activo'));
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:150'],
            'tipo' => ['sometimes', 'in:hardware,software,informacion,servicio,infraestructura'],
            'confidencialidad' => ['sometimes', 'integer', 'between:1,5'],
            'integridad' => ['sometimes', 'integer', 'between:1,5'],
            'disponibilidad' => ['sometimes', 'integer', 'between:1,5'],
            'ubicacion_id' => ['sometimes', 'nullable', 'exists:ubicaciones,id'],
            'responsable_id' => ['sometimes', 'nullable', 'exists:usuarios,id'],
        ];
    }
}