<?php

declare(strict_types=1);

use App\Enums\NivelRiesgo;
use App\Exceptions\NivelRiesgoNoConfiguradoException;
use App\Repositories\Contracts\MatrizRiesgoRepositoryInterface;
use App\Support\MatrizRiesgoResolver;

it('resuelve el nivel consultando la matriz configurable', function () {
    $repo = Mockery::mock(MatrizRiesgoRepositoryInterface::class);
    $repo->shouldReceive('resolverNivel')->with(5, 5)->andReturn(NivelRiesgo::Critico);

    $resolver = new MatrizRiesgoResolver($repo);

    expect($resolver->resolver(5, 5))->toBe(NivelRiesgo::Critico);
});

it('lanza excepción de dominio si la combinación no está configurada', function () {
    $repo = Mockery::mock(MatrizRiesgoRepositoryInterface::class);
    $repo->shouldReceive('resolverNivel')->with(3, 3)->andReturn(null);

    $resolver = new MatrizRiesgoResolver($repo);

    expect(fn () => $resolver->resolver(3, 3))->toThrow(NivelRiesgoNoConfiguradoException::class);
});