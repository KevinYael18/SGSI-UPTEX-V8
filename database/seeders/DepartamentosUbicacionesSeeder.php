<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Ubicacion;
use Illuminate\Database\Seeder;

class DepartamentosUbicacionesSeeder extends Seeder
{
    public function run(): void
    {
        $departamentos = [
            ['clave' => 'RECTORIA', 'nombre' => 'Rectoría'],
            ['clave' => 'DIRACAD', 'nombre' => 'Dirección Académica'],
            ['clave' => 'TI', 'nombre' => 'Tecnologías de la Información'],
            ['clave' => 'SERVESC', 'nombre' => 'Servicios Escolares'],
            ['clave' => 'RH', 'nombre' => 'Recursos Humanos'],
            ['clave' => 'BIBLIO', 'nombre' => 'Biblioteca'],
            ['clave' => 'LABS', 'nombre' => 'Laboratorios'],
            ['clave' => 'VINCUL', 'nombre' => 'Vinculación'],
            ['clave' => 'ADMIN', 'nombre' => 'Administración y Finanzas'],
        ];

        $ubicacionesPorClave = [
            'RECTORIA' => ['Edificio de Rectoría'],
            'TI' => ['Centro de Cómputo 1', 'Sala de Servidores'],
            'LABS' => ['Laboratorio de Redes', 'Laboratorio de Cómputo A', 'Laboratorio de Cómputo B'],
            'BIBLIO' => ['Biblioteca Central'],
            'SERVESC' => ['Edificio de Servicios Escolares'],
        ];

        foreach ($departamentos as $d) {
            $dep = Departamento::query()->firstOrCreate(['clave' => $d['clave']], $d);

            foreach ($ubicacionesPorClave[$d['clave']] ?? ["Oficinas {$d['nombre']}"] as $nombreUbicacion) {
                Ubicacion::query()->firstOrCreate([
                    'departamento_id' => $dep->id,
                    'nombre' => $nombreUbicacion,
                ], ['edificio' => "Edificio {$d['nombre']}"]);
            }
        }
    }
}