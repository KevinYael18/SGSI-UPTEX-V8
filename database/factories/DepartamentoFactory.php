<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Departamento;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepartamentoFactory extends Factory
{
    protected $model = Departamento::class;

    public function definition(): array
    {
        return [
            'clave' => strtoupper($this->faker->unique()->lexify('???')),
            'nombre' => $this->faker->words(2, true),
        ];
    }
}