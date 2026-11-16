<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\Usuario::class)
            && $this->user()->tienePermiso('usuarios.crear')
            && \App\Policies\UsuarioPolicy::puedeAsignarRol($this->user(), $this->input('rol_id'));
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:usuarios,email'],
            // Misma política que «Mi perfil» y la recuperación (bcrypt solo usa 72 bytes: máx. 255).
            'password' => ['required', 'string', 'max:255', \Illuminate\Validation\Rules\Password::min(12)->letters()->mixedCase()->numbers()],
            'rol_id' => ['required', 'exists:roles,id'],
        ];
    }
}