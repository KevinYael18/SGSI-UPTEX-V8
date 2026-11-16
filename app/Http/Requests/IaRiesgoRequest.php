<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Riesgo;
use Illuminate\Foundation\Http\FormRequest;

class IaRiesgoRequest extends FormRequest
{
    /**
     * Solo quien registra o edita riesgos puede pedir sugerencias de IA (cada
     * llamada consume cuota del proveedor externo). El límite de frecuencia lo
     * aplica el limitador `ia-sugerencias` de la ruta.
     */
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario->can('create', Riesgo::class) || $usuario->tienePermiso('riesgos.editar');
    }

    public function rules(): array
    {
        return [
            'activo_id' => ['required', 'integer', 'exists:activos,id'],
            'amenaza_id' => ['required', 'integer', 'exists:amenazas,id'],
            'vulnerabilidad_id' => ['nullable', 'integer', 'exists:vulnerabilidades,id'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            // Al editar un riesgo existente: el análisis queda asociado a él.
            'riesgo_id' => ['nullable', 'integer', 'exists:riesgos,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'activo_id.required' => 'Seleccione el activo del riesgo.',
            'amenaza_id.required' => 'Seleccione la amenaza del riesgo.',
            'descripcion.max' => 'La descripción no puede exceder 500 caracteres.',
        ];
    }
}
