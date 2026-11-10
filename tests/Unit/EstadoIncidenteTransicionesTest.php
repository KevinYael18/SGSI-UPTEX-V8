<?php

declare(strict_types=1);

use App\Enums\EstadoIncidente;

it('un incidente abierto puede pasar a en_analisis o cerrarse directamente', function () {
    $permitidas = EstadoIncidente::Abierto->transicionesPermitidas();

    expect($permitidas)->toContain(EstadoIncidente::EnAnalisis, EstadoIncidente::Cerrado);
});

it('un incidente cerrado no tiene transiciones permitidas', function () {
    expect(EstadoIncidente::Cerrado->transicionesPermitidas())->toBeEmpty();
});