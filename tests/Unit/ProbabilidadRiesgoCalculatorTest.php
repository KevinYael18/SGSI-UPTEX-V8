<?php

declare(strict_types=1);

use App\Enums\CategoriaControlIso;
use App\Models\Activo;
use App\Models\Amenaza;
use App\Models\ControlIso;
use App\Models\Vulnerabilidad;
use App\Support\ProbabilidadRiesgoCalculator;

function crearControlIsoPrueba(string $codigo, string $estado = 'no_implementado'): ControlIso
{
    return ControlIso::create([
        'codigo' => $codigo,
        'nombre' => 'Control de prueba '.$codigo,
        'categoria' => CategoriaControlIso::cases()[0]->value,
        'estado_implementacion' => $estado,
    ]);
}

function calcularProbabilidadPrueba(Activo $activo, string $categoriaAmenaza, string $nombreVulnerabilidad): int
{
    $amenaza = Amenaza::factory()->create(['categoria' => $categoriaAmenaza]);
    $vulnerabilidad = Vulnerabilidad::factory()->create(['nombre' => $nombreVulnerabilidad]);

    return (new ProbabilidadRiesgoCalculator())->calcular($activo, $amenaza, $vulnerabilidad);
}

it('usa la base de la categoría de amenaza si la vulnerabilidad no tiene control mapeado', function () {
    $activo = Activo::factory()->create();

    expect(calcularProbabilidadPrueba($activo, 'ambiental', 'Vulnerabilidad sin mapeo'))->toBe(2);
});

it('suma 1 si el control de la vulnerabilidad no está vinculado al activo', function () {
    $activo = Activo::factory()->create();
    crearControlIsoPrueba('A.5.17');

    expect(calcularProbabilidadPrueba($activo, 'tecnica', 'Contraseñas débiles o reutilizadas'))->toBe(4);
});

it('resta 1 si el control está vinculado al activo y está implementado', function () {
    $activo = Activo::factory()->create();
    $control = crearControlIsoPrueba('A.5.17', 'implementado');
    $activo->controlesIso()->attach($control->id);

    expect(calcularProbabilidadPrueba($activo, 'tecnica', 'Contraseñas débiles o reutilizadas'))->toBe(2);
});

it('no ajusta si el control está vinculado pero no implementado', function () {
    $activo = Activo::factory()->create();
    $control = crearControlIsoPrueba('A.5.17', 'no_implementado');
    $activo->controlesIso()->attach($control->id);

    expect(calcularProbabilidadPrueba($activo, 'tecnica', 'Contraseñas débiles o reutilizadas'))->toBe(3);
});

it('nunca baja de 1 aunque el ajuste sea negativo', function () {
    $activo = Activo::factory()->create();
    $control = crearControlIsoPrueba('A.5.17', 'implementado');
    $activo->controlesIso()->attach($control->id);

    expect(calcularProbabilidadPrueba($activo, 'ambiental', 'Contraseñas débiles o reutilizadas'))->toBe(1);
});
