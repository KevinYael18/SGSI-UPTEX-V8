<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MatrizRiesgo;
use Illuminate\Database\Seeder;

/**
 * Matriz 5x5 por defecto (editable después por el Responsable SGSI desde
 * el sistema, sin tocar código — Fase 1.5 §3).
 */
class MatrizRiesgoSeeder extends Seeder
{
    public function run(): void
    {
        for ($probabilidad = 1; $probabilidad <= 5; $probabilidad++) {
            for ($impacto = 1; $impacto <= 5; $impacto++) {
                $puntaje = $probabilidad * $impacto;
                $nivel = match (true) {
                    $puntaje <= 4 => 'bajo',
                    $puntaje <= 9 => 'medio',
                    $puntaje <= 16 => 'alto',
                    default => 'critico',
                };

                MatrizRiesgo::query()->firstOrCreate(
                    ['probabilidad' => $probabilidad, 'impacto' => $impacto],
                    ['nivel_resultante' => $nivel]
                );
            }
        }
    }
}