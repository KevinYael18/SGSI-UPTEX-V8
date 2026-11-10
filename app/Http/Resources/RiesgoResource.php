<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RiesgoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'activo_id' => $this->activo_id,
            'amenaza_id' => $this->amenaza_id,
            'vulnerabilidad_id' => $this->vulnerabilidad_id,
            'activo' => $this->whenLoaded('activo', fn () => $this->activo->nombre),
            'amenaza' => $this->whenLoaded('amenaza', fn () => $this->amenaza->nombre),
            'vulnerabilidad' => $this->whenLoaded('vulnerabilidad', fn () => $this->vulnerabilidad->nombre),
            'probabilidad' => $this->probabilidad,
            'impacto' => $this->impacto,
            'nivel_calculado' => $this->nivel_calculado?->value,
            'tipo_tratamiento' => $this->tipo_tratamiento?->value,
            'planes_tratamiento' => PlanTratamientoResource::collection($this->whenLoaded('planesTratamiento')),
        ];
    }
}