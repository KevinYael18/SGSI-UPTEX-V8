<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarEdificioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $actual = $this->route('edificio');

        return [
            'nombre' => [$actual ? 'sometimes' : 'required', 'string', 'max:120', Rule::unique('edificios', 'nombre')->ignore($actual?->id)],
            'clave' => ['sometimes', 'nullable', 'string', 'max:20', Rule::unique('edificios', 'clave')->ignore($actual?->id)],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre del edificio.',
            'nombre.unique' => 'Ya existe un edificio con ese nombre.',
            'clave.unique' => 'Ya existe un edificio con esa clave.',
        ];
    }
}
