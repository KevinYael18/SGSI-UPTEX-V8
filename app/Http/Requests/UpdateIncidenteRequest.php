<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIncidenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('incidente'));
    }

    public function rules(): array
    {
        return [
            'titulo' => ['sometimes', 'string', 'max:150'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'activo_id' => ['sometimes', 'nullable', 'exists:activos,id'],
        ];
    }
}