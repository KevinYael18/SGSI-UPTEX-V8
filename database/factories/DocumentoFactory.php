<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Documento;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentoFactory extends Factory
{
    protected $model = Documento::class;

    public function definition(): array
    {
        return [
            'titulo' => $this->faker->sentence(4),
            'estado' => 'borrador',
            'version_actual' => 1,
        ];
    }
}