<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Trait reutilizado por todo modelo que expone UUID público en vez de ID
 * secuencial en las rutas de la API (decisión Fase 1.5 §8).
 */
trait TieneUuidPublico
{
    public static function bootTieneUuidPublico(): void
    {
        static::creating(function ($modelo) {
            if (empty($modelo->uuid)) {
                $modelo->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}