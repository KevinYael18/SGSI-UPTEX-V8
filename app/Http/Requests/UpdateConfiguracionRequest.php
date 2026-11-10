<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConfiguracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionar-configuracion');
    }

    public function rules(): array
    {
        return [
            'valores' => ['required', 'array'],
            'valores.*' => ['required', 'string', 'max:2000'],
        ];
    }
}