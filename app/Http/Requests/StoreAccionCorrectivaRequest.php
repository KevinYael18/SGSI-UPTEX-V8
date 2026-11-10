<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccionCorrectivaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('no_conformidad')?->hallazgo?->auditoria);
    }

    public function rules(): array
    {
        return [
            'responsable_id' => ['required', 'exists:usuarios,id'],
            'fecha_compromiso' => ['required', 'date', 'after:today'],
        ];
    }
}