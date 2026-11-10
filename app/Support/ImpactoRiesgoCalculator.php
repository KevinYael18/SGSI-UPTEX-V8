<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Activo;

/**
 * SRP: única responsabilidad es derivar el impacto (1-5) de un riesgo a partir
 * de la criticidad del activo. Usa la criticidad ya calculada (máximo de C, I, D)
 * y, si no está guardada, la recalcula con CriticidadActivoCalculator.
 *
 * Base documentada: ISO/IEC 27005:2022 — la consecuencia del riesgo se evalúa
 * sobre confidencialidad, integridad y disponibilidad del activo afectado.
 */
final class ImpactoRiesgoCalculator
{
    public function __construct(private readonly CriticidadActivoCalculator $criticidad)
    {
    }

    public function calcular(Activo $activo): int
    {
        if ($activo->criticidad_calculada !== null) {
            return (int) $activo->criticidad_calculada;
        }

        return $this->criticidad->calcular(
            (int) $activo->confidencialidad,
            (int) $activo->integridad,
            (int) $activo->disponibilidad,
        );
    }
}
