<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ControlIsoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'categoria' => $this->categoria->value,
            'clausula' => $this->whenLoaded('clausula', fn () => $this->clausula?->numero),
            'nombre' => $this->nombre,
            'estado_implementacion' => $this->estado_implementacion->value,
        ];
    }
}