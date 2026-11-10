<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanTratamientoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'descripcion' => $this->descripcion,
            'responsable' => $this->whenLoaded('responsable', fn () => $this->responsable?->nombre),
            'fecha_objetivo' => $this->fecha_objetivo?->toDateString(),
            'estado' => $this->estado->value,
        ];
    }
}