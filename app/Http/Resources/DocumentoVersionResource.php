<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentoVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'numero_version' => $this->numero_version,
            'creado_por' => $this->whenLoaded('creadoPor', fn () => $this->creadoPor?->nombre),
            'aprobado_por' => $this->whenLoaded('aprobadoPor', fn () => $this->aprobadoPor?->nombre),
            'fecha_expiracion' => $this->fecha_expiracion?->toDateString(),
        ];
    }
}