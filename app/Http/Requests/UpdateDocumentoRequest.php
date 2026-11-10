<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('documento'));
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:200'],
        ];
    }
}