<?php

declare(strict_types=1);

use App\Support\CriticidadActivoCalculator;

it('la criticidad es el máximo de confidencialidad, integridad y disponibilidad', function () {
    $calculadora = new CriticidadActivoCalculator();

    expect($calculadora->calcular(5, 1, 1))->toBe(5)
        ->and($calculadora->calcular(2, 2, 2))->toBe(2)
        ->and($calculadora->calcular(1, 1, 4))->toBe(4);
});