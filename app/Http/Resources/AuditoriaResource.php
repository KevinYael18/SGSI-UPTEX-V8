<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditoriaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alcance' => $this->alcance,
            'fecha_inicio' => $this->fecha_inicio->toDateString(),
            'fecha_fin' => $this->fecha_fin?->toDateString(),
            'auditor_id' => $this->auditor_id,
            'auditor' => $this->whenLoaded('auditor', fn () => $this->auditor->nombre),
            'hallazgos' => HallazgoResource::collection($this->whenLoaded('hallazgos')),
        ];
    }
}