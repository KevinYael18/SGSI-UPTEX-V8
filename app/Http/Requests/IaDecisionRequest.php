<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\IaAnalisis;
use Illuminate\Foundation\Http\FormRequest;

class IaDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('ia.usar');
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:' . implode(',', IaAnalisis::ESTADOS_DECISION)],
            'elementos_aplicados' => ['nullable', 'array', 'max:20'],
            'elementos_aplicados.*' => ['string', 'max:60'],
        ];
    }
}
