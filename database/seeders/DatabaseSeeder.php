<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesPermisosSeeder::class,
            DepartamentosUbicacionesSeeder::class,
            ClausulasControlesIsoSeeder::class,
            MatrizRiesgoSeeder::class,
            AmenazasVulnerabilidadesSeeder::class,
            UsuariosDemoSeeder::class,
            FeatureFlagsSeeder::class,
            ConfiguracionSistemaSeeder::class,
        ]);
    }
}