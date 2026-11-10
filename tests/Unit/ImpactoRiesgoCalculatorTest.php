<?php

declare(strict_types=1);

use App\Models\Activo;
use App\Support\CriticidadActivoCalculator;
use App\Support\ImpactoRiesgoCalculator;

function calculadoraImpacto(): ImpactoRiesgoCalculator
{
    return new ImpactoRiesgoCalculator(new CriticidadActivoCalculator());
}

it('usa la criticidad guardada del activo como impacto', function () {
    $activo = new Activo(['confidencialidad' => 2, 'integridad' => 2, 'disponibilidad' => 2]);
    $activo->criticidad_calculada = 5;

    expect(calculadoraImpacto()->calcular($activo))->toBe(5);
});

it('recalcula el impacto desde CIA si la criticidad no está guardada', function () {
    $activo = new Activo(['confidencialidad' => 1, 'integridad' => 4, 'disponibilidad' => 2]);
    $activo->criticidad_calculada = null;

    expect(calculadoraImpacto()->calcular($activo))->toBe(4);
});
