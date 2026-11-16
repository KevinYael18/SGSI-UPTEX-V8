<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncidenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Incidente::class);
    }

    public function rules(): array
    {
        return [
            'activo_id' => ['nullable', 'exists:activos,id'],
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:10000'],
            'fecha_deteccion' => ['required', 'date', 'before_or_equal:now'],
            'evidencia' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,mp4'],
        ];
    }
}