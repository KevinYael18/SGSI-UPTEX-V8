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
    private const PASSWORD_DEMO = 'Passw0rd!2026';

    public function run(): void
    {
        $roles = Rol::query()->pluck('id', 'nombre');
        $esEntornoDemo = app()->environment('local', 'testing');

        // Administrador: la contraseña viene de ADMIN_PASSWORD. El valor demo solo
        // se admite en local/testing; en cualquier otro entorno, sin ADMIN_PASSWORD
        // no se crea (así nunca queda una cuenta administradora con contraseña pública).
        $passwordAdmin = env('ADMIN_PASSWORD') ?: ($esEntornoDemo ? self::PASSWORD_DEMO : null);

        if ($passwordAdmin === null) {
            $this->command?->warn('ADMIN_PASSWORD no está definida: no se creó el Administrador. Ejecute `php artisan sgsi:crear-admin`.');
        } else {
            Usuario::query()->firstOrCreate(
                ['email' => 'admin@uptex.edu.mx'],
                [
                    'nombre' => 'Admin UPTex',
                    'password_hash' => Hash::make($passwordAdmin),
                    'rol_id' => $roles['Administrador'],
                    'estado' => 'activo',
                ]
            );
        }

        // Los demás usuarios demo SOLO se crean en entorno local/testing. En producción
        // solo existe el Administrador creado por CrearAdministradorCommand.
        if ($esEntornoDemo) {
            $usuarios = [
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
                        'password_hash' => Hash::make(self::PASSWORD_DEMO),
                        'rol_id' => $roles[$u['rol']],
                        'estado' => 'activo',
                    ]
                );
            }
        }
    }
}
