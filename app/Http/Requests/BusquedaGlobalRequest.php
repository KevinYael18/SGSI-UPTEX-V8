<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BusquedaGlobalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // cualquier usuario autenticado puede buscar; cada módulo filtra por su propia visibilidad después
    }

    public function rules(): array
    {
        return ['q' => ['required', 'string', 'min:2', 'max:100']];
    }
}