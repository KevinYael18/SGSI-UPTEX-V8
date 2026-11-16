<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\InventarioCatalogos as C;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarSoftwareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $actual = $this->route('software');
        $req = $actual ? 'sometimes' : 'required';

        return [
            'nombre' => [$req, 'string', 'max:150',
                Rule::unique('software_catalogo', 'nombre')->where('fabricante', $this->input('fabricante', $actual?->fabricante))->ignore($actual?->id)],
            'fabricante' => ['sometimes', 'nullable', 'string', 'max:120'],
            'categoria' => [$req, 'in:' . C::claves(C::CATEGORIAS_SOFTWARE)],
            'tipo_licencia' => ['sometimes', 'in:' . C::claves(C::TIPOS_LICENCIA)],
            'estado_autorizacion' => ['sometimes', 'in:' . C::claves(C::ESTADOS_AUTORIZACION)],
            'version_vigente' => ['sometimes', 'nullable', 'string', 'max:60'],
            'es_antivirus' => ['sometimes', 'boolean'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre del programa.',
            'nombre.unique' => 'Ese programa ya está en el catálogo con el mismo fabricante.',
            'categoria.required' => 'Selecciona la categoría.',
            '*.in' => 'El valor seleccionado no es válido.',
        ];
    }
}
