<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'titulo' => $this->titulo,
            'estado' => $this->estado->value,
            'version_actual' => $this->version_actual,
            'publicado_por' => $this->whenLoaded('publicadoPor', fn () => $this->publicadoPor?->nombre),
            'publicado_en' => $this->publicado_en?->toIso8601String(),
            'versiones' => DocumentoVersionResource::collection($this->whenLoaded('versiones')),
        ];
    }
}