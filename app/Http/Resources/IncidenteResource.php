<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidenteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado->value,
            'activo_id' => $this->activo_id,
            'activo_responsable_id' => $this->whenLoaded('activo', fn () => $this->activo?->responsable_id),
            'activo' => $this->whenLoaded('activo', fn () => $this->activo?->nombre),
            'reportado_por' => $this->whenLoaded('reportadoPor', fn () => $this->reportadoPor->nombre),
            'fecha_deteccion' => $this->fecha_deteccion->toIso8601String(),
            'evidencias' => EvidenciaResource::collection($this->whenLoaded('evidencias')),
        ];
    }
}