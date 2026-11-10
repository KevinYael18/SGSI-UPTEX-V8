<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Vulnerabilidad;
use Illuminate\Database\Eloquent\Factories\Factory;

class VulnerabilidadFactory extends Factory
{
    protected $model = Vulnerabilidad::class;

    public function definition(): array
    {
        return ['nombre' => $this->faker->sentence(3), 'categoria' => 'general'];
    }
}
