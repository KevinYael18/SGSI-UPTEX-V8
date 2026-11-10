<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('usuario'));
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'email', 'max:150', Rule::unique('usuarios', 'email')->ignore($this->route('usuario')->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:12'],
            'rol_id' => ['sometimes', 'exists:roles,id'],
        ];
    }
}