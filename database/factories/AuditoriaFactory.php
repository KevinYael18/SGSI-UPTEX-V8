<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditoriaFactory extends Factory
{
    protected $model = Auditoria::class;

    public function definition(): array
    {
        return [
            'alcance' => $this->faker->sentence(4),
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addDays(5)->toDateString(),
            'auditor_id' => Usuario::factory(),
        ];
    }
}