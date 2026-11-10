<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Un usuario por rol institucional, para pruebas y defensa (contraseña
 * "Passw0rd!2026" — SOLO para entorno de desarrollo, nunca producción).
 */
class UsuariosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Rol::query()->pluck('id', 'nombre');

        $usuarios = [
            ['nombre' => 'Admin UPTex', 'email' => 'admin@uptex.edu.mx', 'rol' => 'Administrador'],
            ['nombre' => 'Auditor Interno', 'email' => 'auditor@uptex.edu.mx', 'rol' => 'Auditor'],
            ['nombre' => 'Responsable SGSI', 'email' => 'sgsi@uptex.edu.mx', 'rol' => 'Responsable SGSI'],
            ['nombre' => 'Responsable TI', 'email' => 'responsable.ti@uptex.edu.mx', 'rol' => 'Responsable de Activo'],
            ['nombre' => 'Director Académico', 'email' => 'director@uptex.edu.mx', 'rol' => 'Director'],
            ['nombre' => 'Usuario Consulta', 'email' => 'consulta@uptex.edu.mx', 'rol' => 'Consulta'],
        ];

        foreach ($usuarios as $u) {
            Usuario::query()->firstOrCreate(
                ['email' => $u['email']],
                [
                    'nombre' => $u['nombre'],
                    'password_hash' => Hash::make('Passw0rd!2026'),
                    'rol_id' => $roles[$u['rol']],
                    'estado' => 'activo',
                ]
            );
        }
    }
}