<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Departamento;
use App\Models\Activo;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivoFactory extends Factory
{
    protected $model = Activo::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->words(3, true),
            'tipo' => $this->faker->randomElement(['hardware', 'software', 'informacion', 'servicio', 'infraestructura']),
            'confidencialidad' => $this->faker->numberBetween(1, 5),
            'integridad' => $this->faker->numberBetween(1, 5),
            'disponibilidad' => $this->faker->numberBetween(1, 5),
            'departamento_id' => Departamento::factory(),
        ];
    }
}