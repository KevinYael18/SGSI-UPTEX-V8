<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Reemplaza Cache::tags() (solo disponible con Redis/Memcached) por un
 * patrón de "versión de cache" que funciona con CUALQUIER driver, incluido
 * 'database' o 'file' — obligatorio tras el cambio de arquitectura a 2
 * contenedores sin Redis. Invalidar = incrementar el contador; todas las
 * claves construidas con la versión anterior quedan huérfanas (y expiran
 * solas por TTL), sin necesidad de "flush" ni de rastrear claves.
 */
final class CacheVersionado
{
    public static function version(string $espacio): int
    {
        return (int) Cache::get("cache_version:{$espacio}", 1);
    }

    public static function invalidar(string $espacio): void
    {
        // Algunos drivers (p. ej. 'database') devuelven false al incrementar una
        // clave que aún no existe; en ese caso se crea explícitamente.
        if (Cache::increment("cache_version:{$espacio}") === false) {
            Cache::forever("cache_version:{$espacio}", self::version($espacio) + 1);
        }
    }

    public static function clave(string $espacio, string $sufijo): string
    {
        return "{$espacio}:v" . self::version($espacio) . ":{$sufijo}";
    }
}