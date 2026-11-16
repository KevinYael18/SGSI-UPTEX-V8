<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Activo::class);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'tipo' => ['required', 'in:hardware,software,informacion,servicio,infraestructura'],
            'confidencialidad' => ['required', 'integer', 'between:1,5'],
            'integridad' => ['required', 'integer', 'between:1,5'],
            'disponibilidad' => ['required', 'integer', 'between:1,5'],
            'departamento_id' => ['required', 'exists:departamentos,id'],
            'ubicacion_id' => ['nullable', 'exists:ubicaciones,id'],
            'responsable_id' => ['nullable', 'exists:usuarios,id'],
            'controles_iso_ids' => ['sometimes', 'array'],
            'controles_iso_ids.*' => ['exists:controles_iso,id'],
            // Ficha técnica dinámica (validación detallada en FichaTecnicaActivoService).
            'subtipo' => ['sometimes', 'nullable', 'string', 'max:40'],
            'tecnico' => ['sometimes', 'nullable', 'array'],
            'personalizados' => ['sometimes', 'nullable', 'array', 'max:30'],
            'personalizados.*.nombre' => ['nullable', 'string', 'max:60'],
            'personalizados.*.valor' => ['nullable', 'string', 'max:255'],
            'ubicacion_detalle' => ['sometimes', 'nullable', 'string', 'max:150'],
        ];
    }
}