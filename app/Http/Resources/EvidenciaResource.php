<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EvidenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $disco = Storage::disk(config('filesystems.default'));
        $disponible = $disco->exists($this->path_storage);

        return [
            'uuid' => $this->uuid,
            'mime_type' => $this->mime_type,
            'hash_sha256' => $this->hash_sha256,
            'fecha_expiracion' => $this->fecha_expiracion?->toDateString(),
            'fecha_subida' => $this->created_at?->toIso8601String(),
            // false cuando el registro existe pero el archivo físico no (p. ej. tras restaurar solo la BD).
            'archivo_disponible' => $disponible,
            'tamano_bytes' => $disponible ? $disco->size($this->path_storage) : null,
            'url_descarga' => "/v1/evidencias/{$this->uuid}/descargar",
        ];
    }
}