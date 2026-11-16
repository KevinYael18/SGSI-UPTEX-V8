<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\InventarioCatalogos as C;
use Illuminate\Foundation\Http\FormRequest;

class MantenimientoEquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'in:' . C::claves(C::TIPOS_MANTENIMIENTO)],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'descripcion' => ['required', 'string', 'max:2000'],
            'realizado_por' => ['sometimes', 'nullable', 'string', 'max:150'],
            'estado_resultante' => ['sometimes', 'nullable', 'in:' . C::claves(C::ESTADOS_OPERATIVOS)],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'Selecciona el tipo de mantenimiento.',
            'fecha.required' => 'Indica la fecha del mantenimiento.',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
            'descripcion.required' => 'Describe el trabajo realizado.',
        ];
    }
}
