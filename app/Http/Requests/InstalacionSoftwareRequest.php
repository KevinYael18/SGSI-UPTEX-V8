<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\InventarioCatalogos as C;
use Illuminate\Foundation\Http\FormRequest;

class InstalacionSoftwareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'software_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'integer', 'exists:software_catalogo,id'],
            'version' => ['sometimes', 'nullable', 'string', 'max:60'],
            'fecha_instalacion' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'estado_licencia' => ['sometimes', 'in:' . C::claves(C::ESTADOS_LICENCIA)],
            'requiere_actualizacion' => ['sometimes', 'boolean'],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'software_id.required' => 'Selecciona un programa del catálogo.',
            'software_id.exists' => 'El programa seleccionado no existe en el catálogo.',
            '*.in' => 'El valor seleccionado no es válido.',
            '*.before_or_equal' => 'La fecha no puede ser futura.',
        ];
    }
}
