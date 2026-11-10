<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AccionCorrectiva;
use App\Models\Auditoria;
use App\Models\Incidente;
use App\Models\Riesgo;
use Illuminate\Database\Eloquent\Model;

/**
 * Whitelist explícita de qué modelos aceptan comentarios/timeline y bajo
 * qué segmento de URL — evita exponer un endpoint polimórfico genérico
 * que aceptaría cualquier tabla del sistema como comentable.
 *
 * Corrección (detectada al integrar el frontend): la búsqueda se hace por
 * getRouteKeyName() de cada modelo, NO por id numérico fijo. Incidente
 * (y cualquier entidad futura con TieneUuidPublico) expone solo UUID al
 * frontend — buscar siempre por 'id' habría hecho estos endpoints
 * inutilizables desde la SPA para esas entidades.
 */
final class RegistroComentables
{
    private const MAPA = [
        'incidentes' => Incidente::class,
        'riesgos' => Riesgo::class,
        'auditorias' => Auditoria::class,
        'acciones-correctivas' => AccionCorrectiva::class,
    ];

    public static function resolverClase(string $tipo): ?string
    {
        return self::MAPA[$tipo] ?? null;
    }

    public static function tiposValidos(): array
    {
        return array_keys(self::MAPA);
    }

    public static function resolverModelo(string $tipo, string $identificador): ?Model
    {
        $clase = self::resolverClase($tipo);

        if ($clase === null) {
            return null;
        }

        /** @var Model $instancia */
        $instancia = new $clase();

        return $clase::query()->where($instancia->getRouteKeyName(), $identificador)->first();
    }
}