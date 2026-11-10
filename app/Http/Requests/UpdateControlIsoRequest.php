<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateControlIsoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('control_iso'));
    }

    public function rules(): array
    {
        return [
            'estado_implementacion' => ['required', 'in:no_implementado,en_proceso,implementado,no_aplica'],
        ];
    }
}