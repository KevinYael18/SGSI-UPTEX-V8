<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccionCorrectivaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'responsable' => $this->whenLoaded('responsable', fn () => $this->responsable->nombre),
            'fecha_compromiso' => $this->fecha_compromiso->toDateString(),
            'estado' => $this->estado->value,
        ];
    }
}