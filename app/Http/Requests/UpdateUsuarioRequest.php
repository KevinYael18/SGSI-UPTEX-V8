<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('usuario'))
            && \App\Policies\UsuarioPolicy::puedeAsignarRol($this->user(), $this->input('rol_id'));
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'email', 'max:150', Rule::unique('usuarios', 'email')->ignore($this->route('usuario')->id)],
            'password' => ['sometimes', 'nullable', 'string', 'max:255', \Illuminate\Validation\Rules\Password::min(12)->letters()->mixedCase()->numbers()],
            'rol_id' => ['sometimes', 'exists:roles,id'],
        ];
    }
}