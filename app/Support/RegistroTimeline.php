<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Activo;
use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\Incidente;
use App\Models\Riesgo;
use Illuminate\Database\Eloquent\Model;

/**
 * Whitelist de entidades con timeline (activity_log). Igual que
 * RegistroComentables: resuelve por getRouteKeyName() del modelo (uuid o
 * id según la entidad), nunca asume id numérico fijo.
 */
final class RegistroTimeline
{
    private const MAPA = [
        'activos' => Activo::class,
        'riesgos' => Riesgo::class,
        'incidentes' => Incidente::class,
        'auditorias' => Auditoria::class,
        'documentos' => Documento::class,
    ];

    public static function tiposValidos(): array
    {
        return array_keys(self::MAPA);
    }

    public static function resolverModelo(string $tipo, string $identificador): ?Model
    {
        $clase = self::MAPA[$tipo] ?? null;

        if ($clase === null) {
            return null;
        }

        /** @var Model $instancia */
        $instancia = new $clase();

        return $clase::query()->where($instancia->getRouteKeyName(), $identificador)->first();
    }

    public static function nombreTabla(string $tipo): ?string
    {
        $clase = self::MAPA[$tipo] ?? null;

        return $clase ? (new $clase())->getTable() : null;
    }
}