<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConfiguracionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'clave' => $this->clave,
            'valor' => $this->valor,
            'tipo' => $this->tipo,
            'descripcion' => $this->descripcion,
        ];
    }
}