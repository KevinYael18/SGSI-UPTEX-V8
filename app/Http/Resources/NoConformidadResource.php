<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoConformidadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estado' => $this->estado->value,
            'acciones_correctivas' => AccionCorrectivaResource::collection($this->whenLoaded('accionesCorrectivas')),
        ];
    }
}