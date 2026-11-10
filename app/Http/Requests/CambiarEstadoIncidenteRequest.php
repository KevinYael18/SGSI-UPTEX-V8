<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoIncidenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('incidente'));
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'in:en_analisis,contenido,resuelto,cerrado'],
        ];
    }
}