<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HallazgoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'severidad' => $this->severidad->value,
            'descripcion' => $this->descripcion,
            'no_conformidades' => NoConformidadResource::collection($this->whenLoaded('noConformidades')),
        ];
    }
}