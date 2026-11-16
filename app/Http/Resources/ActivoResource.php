<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\FichaTecnicaActivoService;
use App\Support\CatalogoTiposActivo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo->value,
            'cia' => [
                'confidencialidad' => $this->confidencialidad,
                'integridad' => $this->integridad,
                'disponibilidad' => $this->disponibilidad,
            ],
            'criticidad_calculada' => $this->criticidad_calculada,
            'departamento_id' => $this->departamento_id,
            'ubicacion_id' => $this->ubicacion_id,
            'responsable_id' => $this->responsable_id,
            'departamento' => $this->whenLoaded('departamento', fn () => $this->departamento->nombre),
            'ubicacion' => $this->whenLoaded('ubicacion', fn () => $this->ubicacion?->nombre),
            'responsable' => $this->whenLoaded('responsable', fn () => $this->responsable?->nombre),
            'codigo_qr' => $this->codigo_qr,
            // ---- Ficha técnica dinámica (null en activos registrados antes de la ampliación)
            'subtipo' => $this->subtipo,
            'subtipo_etiqueta' => CatalogoTiposActivo::etiqueta($this->subtipo),
            'personalizados' => $this->caracteristicas['personalizados'] ?? [],
            'ubicacion_detalle' => $this->ubicacion_detalle,
            'ubicacion_completa' => $this->whenLoaded('ubicacion', fn () => $this->ubicacion?->descripcion()),
            // Valores técnicos (ficha de cómputo + campos del tipo); solo en el detalle.
            'tecnico' => $this->when($this->relationLoaded('computadora'), fn () => FichaTecnicaActivoService::valoresTecnicos($this->resource)),
            'computadora_uuid' => $this->when($this->relationLoaded('computadora'), fn () => $this->computadora?->uuid),
            // Software: entrada del catálogo y equipos donde está instalado.
            'programa' => $this->when($this->relationLoaded('programaCatalogo') && $this->programaCatalogo !== null, fn () => [
                'id' => $this->programaCatalogo->id,
                'estado_autorizacion' => $this->programaCatalogo->estado_autorizacion,
                'equipos' => $this->programaCatalogo->computadoras->map(fn ($c) => [
                    'uuid' => $c->uuid,
                    'nombre_equipo' => $c->nombre_equipo,
                    'activo' => $c->activo?->nombre,
                    'laboratorio' => $c->laboratorio ? $c->laboratorio->nombre . ($c->laboratorio->edificio ? ' · ' . $c->laboratorio->edificio->nombre : '') : null,
                    'version' => $c->pivot->version,
                    'estado_licencia' => $c->pivot->estado_licencia,
                    'requiere_actualizacion' => (bool) $c->pivot->requiere_actualizacion,
                ])->values(),
            ]),
            'creado_en' => $this->created_at->toIso8601String(),
        ];
    }
}