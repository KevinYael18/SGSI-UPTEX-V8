<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarLaboratorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $actual = $this->route('laboratorio');
        $req = $actual === null ? 'required' : 'sometimes';
        $edificioId = $this->input('edificio_id', $actual?->edificio_id);

        return [
            'edificio_id' => [$req, 'integer', 'exists:edificios,id'],
            'departamento_id' => [$req, 'integer', 'exists:departamentos,id'],
            'responsable_id' => ['sometimes', 'nullable', 'integer', 'exists:usuarios,id'],
            'nombre' => [$req, 'string', 'max:120',
                Rule::unique('laboratorios', 'nombre')->where('edificio_id', $edificioId)->ignore($actual?->id)],
            'clave' => ['sometimes', 'nullable', 'string', 'max:30'],
            'capacidad' => ['sometimes', 'nullable', 'integer', 'between:1,1000'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe un laboratorio con ese nombre en el edificio.',
            '*.required' => 'Este campo es obligatorio.',
            '*.exists' => 'El registro seleccionado no existe.',
        ];
    }
}
