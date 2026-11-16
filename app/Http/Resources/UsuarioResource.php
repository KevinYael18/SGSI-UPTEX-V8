<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'nombre' => $this->nombre,
            'email' => $this->email,
            'rol' => $this->whenLoaded('rol', fn () => $this->rol->nombre),
            // Usado por el frontend SOLO para mostrar/ocultar UI (nunca para
            // autorizar de verdad — eso vive en las Policies del backend).
            'permisos' => $this->whenLoaded(
                'rol',
                fn () => $this->rol->relationLoaded('permisos') ? $this->rol->permisos->pluck('clave') : []
            ),
            'estado' => $this->estado->value,
            'ultimo_acceso' => $this->ultimo_acceso?->toIso8601String(),
            // Trazabilidad de la suspensión (solo se envía si la cuenta está suspendida).
            'suspension' => $this->when($this->estado === \App\Enums\EstadoUsuario::Suspendido, fn () => [
                'desde' => $this->suspendido_at?->toIso8601String(),
                'motivo' => $this->motivo_suspension,
                'por' => $this->suspendidoPor?->nombre,
            ]),
            // Módulo «Mi perfil»: URL pública (relativa) de la foto, o null → avatar con iniciales.
            'foto_url' => \App\Services\PerfilService::urlPublica($this->foto_perfil_path),
        ];
    }
}