<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Rol;
use Illuminate\Database\Eloquent\Factories\Factory;

class RolFactory extends Factory
{
    protected $model = Rol::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->unique()->jobTitle(),
            'descripcion' => $this->faker->sentence(),
        ];
    }
}