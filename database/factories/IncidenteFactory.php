<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Activo;
use App\Models\Incidente;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncidenteFactory extends Factory
{
    protected $model = Incidente::class;

    public function definition(): array
    {
        return [
            'activo_id' => Activo::factory(),
            'reportado_por' => Usuario::factory(),
            'titulo' => $this->faker->sentence(4),
            'descripcion' => $this->faker->paragraph(),
            'fecha_deteccion' => now(),
        ];
    }
}