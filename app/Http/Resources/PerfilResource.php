<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\PerfilService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vista ampliada del usuario autenticado para la pantalla «Mi perfil».
 * Solo datos que el sistema realmente almacena. Nunca incluye password_hash,
 * intentos fallidos, bloqueo ni tokens.
 */
class PerfilResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = (new UsuarioResource($this->resource))->toArray($request);

        return array_merge($base, [
            'rol_descripcion' => $this->whenLoaded('rol', fn () => $this->rol->descripcion),
            'creado_en' => $this->created_at?->toIso8601String(),
            'actualizado_en' => $this->updated_at?->toIso8601String(),
            'departamentos' => $this->whenLoaded(
                'departamentosConAlcance',
                fn () => $this->departamentosConAlcance->map(fn ($d) => ['clave' => $d->clave, 'nombre' => $d->nombre])->values()
            ),
            'resumen' => [
                'activos_responsable' => (int) ($this->activos_responsable_count ?? 0),
                'incidentes_reportados' => (int) ($this->incidentes_reportados_count ?? 0),
            ],
            'politica_password' => [
                'min' => 12,
                'mayusculas_minusculas' => true,
                'numeros' => true,
            ],
            'foto_max_kb' => \App\Http\Requests\SubirFotoPerfilRequest::MAX_KB,
            'foto_url' => PerfilService::urlPublica($this->foto_perfil_path),
        ]);
    }
}
