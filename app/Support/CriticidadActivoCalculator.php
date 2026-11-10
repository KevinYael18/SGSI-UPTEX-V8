<?php

declare(strict_types=1);

namespace App\Support;

/**
 * OCP: la fórmula de criticidad vive aislada aquí. Si mañana cambia
 * (p. ej. de promedio a máximo, o se pondera CIA distinto), se modifica
 * SOLO esta clase — ActivoService no cambia ni una línea.
 */
final class CriticidadActivoCalculator {

    public function calcular(int $confidencialidad, int $integridad, int $disponibilidad): int
    {
        // Criterio: el mayor de los 3 valores domina (peor caso), no el promedio,
        // porque un activo con Confidencialidad=5 es crítico aunque Disponibilidad=1.
        return max($confidencialidad, $integridad, $disponibilidad);
    }
}