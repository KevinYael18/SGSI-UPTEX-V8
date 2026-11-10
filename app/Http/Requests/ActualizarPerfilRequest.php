<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos personales que el propio usuario puede editar.
 * Lista blanca: SOLO `nombre`. El correo es el identificador de acceso y el
 * sistema no tiene mecanismo de verificación de correo, por lo que su cambio
 * queda reservado a la administración de usuarios (igual que rol y estado).
 * Cualquier otro campo enviado (rol_id, estado, id, email, permisos…) se
 * ignora porque el controlador solo usa `validated()`.
 */
class ActualizarPerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nombre'))) {
            // Colapsa espacios repetidos y recorta extremos.
            $this->merge(['nombre' => trim((string) preg_replace('/\s+/u', ' ', $this->input('nombre')))]);
        }
    }

    public function rules(): array
    {
        return [
            // Letras (con acentos), espacios, punto, apóstrofo y guion.
            'nombre' => ['required', 'string', 'min:3', 'max:150', "regex:/^[\\p{L}\\p{M}][\\p{L}\\p{M} .'\\-]*$/u"],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres.',
            'nombre.max' => 'El nombre no puede exceder 150 caracteres.',
            'nombre.regex' => 'El nombre solo puede contener letras, espacios, puntos, apóstrofos y guiones.',
        ];
    }
}
