<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Amenaza;
use Illuminate\Database\Eloquent\Factories\Factory;

class AmenazaFactory extends Factory
{
    protected $model = Amenaza::class;

    public function definition(): array
    {
        return ['nombre' => $this->faker->sentence(3), 'categoria' => 'general'];
    }
}