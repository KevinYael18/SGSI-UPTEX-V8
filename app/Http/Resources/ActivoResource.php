<?php

declare(strict_types=1);

namespace App\Http\Resources;

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
            'creado_en' => $this->created_at->toIso8601String(),
        ];
    }
}