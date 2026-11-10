<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComentarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contenido' => $this->contenido,
            'autor' => $this->whenLoaded('usuario', fn () => $this->usuario->nombre),
            // Aditivo (módulo «Mi perfil»): foto del autor para su avatar, o null.
            'autor_foto_url' => $this->whenLoaded('usuario', fn () => \App\Services\PerfilService::urlPublica($this->usuario?->foto_perfil_path)),
            'creado_en' => $this->created_at->toIso8601String(),
        ];
    }
}