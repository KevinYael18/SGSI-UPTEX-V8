<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\InstalacionSoftware;
use App\Support\InventarioCatalogos as C;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Computadora */
class ComputadoraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $softwareCargado = $this->relationLoaded('software');

        return [
            'uuid' => $this->uuid,
            'activo_uuid' => $this->activo?->uuid,
            'activo_id' => $this->activo_id,
            'nombre_equipo' => $this->nombre_equipo,
            'numero_inventario' => $this->numero_inventario,
            'numero_serie' => $this->numero_serie,
            'direccion_mac' => $this->direccion_mac,
            'direccion_ip' => $this->direccion_ip,
            'ubicacion_fisica' => $this->ubicacion_fisica,
            'estado_operativo' => $this->estado_operativo,
            'estado_operativo_label' => C::ESTADOS_OPERATIVOS[$this->estado_operativo] ?? $this->estado_operativo,
            'laboratorio' => $this->laboratorio ? [
                'id' => $this->laboratorio->id,
                'nombre' => $this->laboratorio->nombre,
                'edificio_id' => $this->laboratorio->edificio_id,
                'edificio' => $this->laboratorio->edificio?->nombre,
            ] : null,
            'hardware' => [
                'fabricante' => $this->fabricante, 'modelo' => $this->modelo, 'procesador' => $this->procesador,
                'ram_gb' => $this->ram_gb, 'almacenamiento_gb' => $this->almacenamiento_gb,
                'tipo_almacenamiento' => $this->tipo_almacenamiento, 'tarjeta_grafica' => $this->tarjeta_grafica,
                'fecha_adquisicion' => $this->fecha_adquisicion?->toDateString(), 'garantia_hasta' => $this->garantia_hasta?->toDateString(),
            ],
            'sistema_operativo' => [
                'nombre' => $this->so_nombre, 'edicion' => $this->so_edicion, 'version' => $this->so_version,
                'arquitectura' => $this->so_arquitectura, 'activacion' => $this->so_activacion,
                'ultima_actualizacion' => $this->so_ultima_actualizacion?->toDateString(), 'soporte' => $this->so_soporte,
            ],
            'antivirus' => [
                'nombre' => $this->antivirus_nombre, 'fabricante' => $this->antivirus_fabricante, 'version' => $this->antivirus_version,
                'instalacion' => $this->antivirus_instalacion, 'proteccion' => $this->antivirus_proteccion,
                'ultima_actualizacion' => $this->antivirus_ultima_actualizacion?->toDateString(),
                'estado' => $this->estadoAntivirus(),
            ],
            'software_total' => $softwareCargado ? $this->software->count() : null,
            'software_pendiente' => $softwareCargado ? $this->softwarePendienteActualizacion() : null,
            'observaciones' => $this->observaciones,
            'activo' => $this->whenLoaded('activo', fn () => [
                'uuid' => $this->activo->uuid,
                'confidencialidad' => $this->activo->confidencialidad,
                'integridad' => $this->activo->integridad,
                'disponibilidad' => $this->activo->disponibilidad,
                'criticidad' => $this->activo->criticidad_calculada,
                'departamento_id' => $this->activo->departamento_id,
                'departamento' => $this->activo->departamento?->nombre,
                'responsable_id' => $this->activo->responsable_id,
                'responsable' => $this->activo->responsable?->nombre,
                'riesgos' => $this->activo->riesgos_count ?? null,
                'incidentes' => $this->activo->incidentes_count ?? null,
                'controles_iso' => $this->activo->relationLoaded('controlesIso')
                    ? $this->activo->controlesIso->map(fn ($c) => ['id' => $c->id, 'codigo' => $c->codigo, 'nombre' => $c->nombre, 'estado' => $c->estado_implementacion])->values()
                    : null,
            ]),
            // Detalle de instalaciones solo en la ficha completa (carga también mantenimientos).
            'software' => $this->when($softwareCargado && $this->relationLoaded('mantenimientos'), fn () => $this->software->map(fn ($s) => [
                    'software_id' => $s->id,
                    'nombre' => $s->nombre,
                    'fabricante' => $s->fabricante,
                    'categoria' => $s->categoria,
                    'estado_autorizacion' => $s->estado_autorizacion,
                    'version_vigente' => $s->version_vigente,
                    'version' => $s->pivot->version,
                    'fecha_instalacion' => $s->pivot->fecha_instalacion ? substr((string) $s->pivot->fecha_instalacion, 0, 10) : null,
                    'estado_licencia' => $s->pivot->estado_licencia,
                    'requiere_actualizacion' => (bool) $s->pivot->requiere_actualizacion,
                    'desactualizado' => InstalacionSoftware::estaDesactualizada((bool) $s->pivot->requiere_actualizacion, $s->pivot->version, $s->version_vigente),
                    'observaciones' => $s->pivot->observaciones,
                ])->values()),
            'mantenimientos' => $this->whenLoaded('mantenimientos', fn () => $this->mantenimientos->map(fn ($m) => [
                'id' => $m->id, 'tipo' => $m->tipo, 'fecha' => $m->fecha->toDateString(), 'descripcion' => $m->descripcion,
                'realizado_por' => $m->realizado_por, 'estado_resultante' => $m->estado_resultante,
                'registrado_por' => $m->registradoPor?->nombre,
            ])->values()),
            'actualizado_en' => $this->updated_at?->toIso8601String(),
        ];
    }
}
