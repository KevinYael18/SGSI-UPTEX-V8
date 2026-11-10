<?php

declare(strict_types=1);

use App\Enums\CategoriaControlIso;
use App\Repositories\Contracts\ControlIsoRepositoryInterface;
use App\Support\ComplianceCalculator;
use Illuminate\Support\Collection;

it('calcula el porcentaje general excluyendo los controles marcados como no_aplica', function () {
    $repo = Mockery::mock(ControlIsoRepositoryInterface::class);
    $repo->shouldReceive('conteoPorEstado')->andReturn([
        'implementado' => 40,
        'en_proceso' => 10,
        'no_implementado' => 40,
        'no_aplica' => 3,
    ]);
    // total=93, aplicables=90, implementados=40 -> 44.4%
    $repo->shouldReceive('porCategoria')->andReturn(new Collection());

    $calc = new ComplianceCalculator($repo);

    expect($calc->porcentajeGeneral())->toBe(44.4);
});

it('devuelve 0 si no hay controles cargados', function () {
    $repo = Mockery::mock(ControlIsoRepositoryInterface::class);
    $repo->shouldReceive('conteoPorEstado')->andReturn([]);

    $calc = new ComplianceCalculator($repo);

    expect($calc->porcentajeGeneral())->toBe(0.0);
});