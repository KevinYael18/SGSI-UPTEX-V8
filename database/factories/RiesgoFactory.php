<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Activo;
use App\Models\Amenaza;
use App\Models\Riesgo;
use App\Models\Vulnerabilidad;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiesgoFactory extends Factory
{
    protected $model = Riesgo::class;

    public function definition(): array
    {
        return [
            'activo_id' => Activo::factory(),
            'amenaza_id' => Amenaza::factory(),
            'vulnerabilidad_id' => Vulnerabilidad::factory(),
            'probabilidad' => $this->faker->numberBetween(1, 5),
            'impacto' => $this->faker->numberBetween(1, 5),
        ];
    }
}