<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Computadora;
use App\Support\InventarioCatalogos as C;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta (POST) y edición (PUT) de la ficha técnica de una computadora.
 * La autorización se resuelve en el controlador (Gate/ActivoPolicy).
 */
class GuardarComputadoraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $mac = $this->input('direccion_mac');
        if (is_string($mac) && trim($mac) !== '') {
            $limpia = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac));
            if (strlen($limpia) === 12) {
                $this->merge(['direccion_mac' => implode(':', str_split($limpia, 2))]);
            }
        }
        foreach (['numero_inventario', 'numero_serie', 'direccion_mac', 'direccion_ip'] as $campo) {
            if ($this->has($campo) && is_string($this->input($campo)) && trim($this->input($campo)) === '') {
                $this->merge([$campo => null]);
            }
        }
    }

    public function rules(): array
    {
        /** @var Computadora|null $actual */
        $actual = $this->route('computadora');
        $esAlta = $actual === null;
        $req = $esAlta ? 'required' : 'sometimes';
        $idActual = $actual?->id;
        $vinculaActivo = $esAlta && $this->filled('activo_id');
        $reqCia = $esAlta && !$vinculaActivo ? 'required' : 'sometimes';

        return [
            'activo_id' => ['sometimes', 'nullable', 'integer', 'exists:activos,id'],
            'laboratorio_id' => ['sometimes', 'nullable', 'integer', 'exists:laboratorios,id'],
            'nombre_equipo' => [$req, 'string', 'max:100'],
            'numero_inventario' => ['sometimes', 'nullable', 'string', 'max:60', Rule::unique('computadoras', 'numero_inventario')->ignore($idActual)],
            'numero_serie' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('computadoras', 'numero_serie')->ignore($idActual)],
            'direccion_mac' => ['sometimes', 'nullable', 'regex:/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', Rule::unique('computadoras', 'direccion_mac')->ignore($idActual)],
            'direccion_ip' => ['sometimes', 'nullable', 'ip'],
            'ubicacion_fisica' => ['sometimes', 'nullable', 'string', 'max:120'],
            'estado_operativo' => ['sometimes', 'in:' . C::claves(C::ESTADOS_OPERATIVOS)],

            'fabricante' => ['sometimes', 'nullable', 'string', 'max:80'],
            'modelo' => ['sometimes', 'nullable', 'string', 'max:100'],
            'procesador' => ['sometimes', 'nullable', 'string', 'max:150'],
            'ram_gb' => ['sometimes', 'nullable', 'integer', 'between:1,4096'],
            'almacenamiento_gb' => ['sometimes', 'nullable', 'integer', 'between:1,1048576'],
            'tipo_almacenamiento' => ['sometimes', 'nullable', 'in:' . C::claves(C::TIPOS_ALMACENAMIENTO)],
            'tarjeta_grafica' => ['sometimes', 'nullable', 'string', 'max:150'],
            'fecha_adquisicion' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'garantia_hasta' => ['sometimes', 'nullable', 'date'],

            'so_nombre' => ['sometimes', 'nullable', 'string', 'max:80'],
            'so_edicion' => ['sometimes', 'nullable', 'string', 'max:80'],
            'so_version' => ['sometimes', 'nullable', 'string', 'max:60'],
            'so_arquitectura' => ['sometimes', 'nullable', 'in:' . C::claves(C::ARQUITECTURAS)],
            'so_activacion' => ['sometimes', 'in:' . C::claves(C::ACTIVACION_SO)],
            'so_ultima_actualizacion' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'so_soporte' => ['sometimes', 'in:' . C::claves(C::SOPORTE_SO)],

            'antivirus_nombre' => ['sometimes', 'nullable', 'string', 'max:100'],
            'antivirus_fabricante' => ['sometimes', 'nullable', 'string', 'max:100'],
            'antivirus_version' => ['sometimes', 'nullable', 'string', 'max:60'],
            'antivirus_instalacion' => ['sometimes', 'in:' . C::claves(C::INSTALACION_ANTIVIRUS)],
            'antivirus_proteccion' => ['sometimes', 'in:' . C::claves(C::PROTECCION_ANTIVIRUS)],
            'antivirus_ultima_actualizacion' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:2000'],

            // Datos del Activo asociado (valoración CIA, departamento, responsable, controles).
            'confidencialidad' => [$reqCia, 'integer', 'between:1,5'],
            'integridad' => [$reqCia, 'integer', 'between:1,5'],
            'disponibilidad' => [$reqCia, 'integer', 'between:1,5'],
            'departamento_id' => ['sometimes', 'nullable', 'integer', 'exists:departamentos,id'],
            'responsable_id' => ['sometimes', 'nullable', 'integer', 'exists:usuarios,id'],
            'controles_iso_ids' => ['sometimes', 'array'],
            'controles_iso_ids.*' => ['integer', 'exists:controles_iso,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_equipo.required' => 'Escribe el nombre del equipo.',
            'numero_inventario.unique' => 'Ya existe un equipo con ese número de inventario.',
            'numero_serie.unique' => 'Ya existe un equipo con ese número de serie.',
            'direccion_mac.unique' => 'Ya existe un equipo con esa dirección MAC.',
            'direccion_mac.regex' => 'La dirección MAC debe tener 12 dígitos hexadecimales (p. ej. 00:1A:2B:3C:4D:5E).',
            'direccion_ip.ip' => 'La dirección IP no es válida.',
            '*.required' => 'Este campo es obligatorio.',
            '*.in' => 'El valor seleccionado no es válido.',
            '*.between' => 'El valor está fuera del rango permitido.',
            '*.before_or_equal' => 'La fecha no puede ser futura.',
            '*.date' => 'La fecha no es válida.',
            '*.exists' => 'El registro seleccionado no existe.',
        ];
    }
}
