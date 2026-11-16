<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Tamaño de página acotado (OWASP API4:2023 — consumo de recursos sin límite).
 * Antes `?por_pagina=1000000` devolvía tablas completas en una sola respuesta.
 * El máximo (100) cubre el mayor uso legítimo del frontend (Cumplimiento: 93 controles).
 */
final class Paginacion
{
    public const MAXIMO = 100;

    public static function porPagina(Request $request, int $defecto = 15): int
    {
        return self::acotar((int) $request->integer('por_pagina', $defecto));
    }

    public static function acotar(int $porPagina): int
    {
        return max(1, min(self::MAXIMO, $porPagina));
    }
}
