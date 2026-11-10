<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\RegistroComentables;

class StoreComentarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Cualquier usuario autenticado con permiso de "ver" el módulo padre
        // puede comentar; se revisa en el Controller vía Policy del padre
        // (no se duplica lógica de permisos por módulo aquí).
        return true;
    }

    public function rules(): array
    {
        return [
            'contenido' => ['required', 'string', 'max:2000'],
        ];
    }
}