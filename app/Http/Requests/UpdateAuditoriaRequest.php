<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAuditoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('auditoria'));
    }

    public function rules(): array
    {
        return [
            'alcance' => ['sometimes', 'string', 'max:255'],
            'fecha_inicio' => ['sometimes', 'date'],
            'fecha_fin' => ['sometimes', 'nullable', 'date', 'after_or_equal:fecha_inicio'],
            'auditor_id' => ['sometimes', 'exists:usuarios,id'],
        ];
    }
}