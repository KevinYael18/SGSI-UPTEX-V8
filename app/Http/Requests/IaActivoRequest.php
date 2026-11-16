<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Activo;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Análisis de un activo existente (activo_uuid) o del borrador del formulario.
 * Analizar un borrador exige poder crear o editar activos; un activo existente,
 * poder verlo (se verifica en el controlador con ActivoPolicy::view).
 */
class IaActivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        if (!$u->can('ia.usar')) {
            return false;
        }
        if ($this->filled('activo_uuid')) {
            return true;
        }

        return $u->can('create', Activo::class) || $u->tienePermiso('activos.editar') || $u->tienePermiso('activos.editar_propio');
    }

    public function rules(): array
    {
        return [
            'activo_uuid' => ['nullable', 'uuid'],
            'borrador' => ['required_without:activo_uuid', 'array'],
            'borrador.nombre' => ['nullable', 'string', 'max:150'],
            'borrador.tipo' => ['nullable', 'string', 'in:hardware,software,informacion,servicio,infraestructura'],
            'borrador.subtipo' => ['nullable', 'string', 'max:40'],
            'borrador.caracteristicas' => ['nullable', 'array'],
            'borrador.confidencialidad' => ['nullable', 'integer', 'between:1,5'],
            'borrador.integridad' => ['nullable', 'integer', 'between:1,5'],
            'borrador.disponibilidad' => ['nullable', 'integer', 'between:1,5'],
            'borrador.departamento_id' => ['nullable', 'integer'],
            'borrador.ubicacion_id' => ['nullable', 'integer'],
            'borrador.responsable_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return ['borrador.required_without' => 'Capture al menos el nombre o el tipo del activo para analizarlo.'];
    }
}
