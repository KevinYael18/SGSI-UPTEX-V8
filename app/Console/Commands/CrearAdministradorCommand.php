<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * php artisan sgsi:crear-admin
 * Lee ADMIN_NOMBRE/ADMIN_EMAIL/ADMIN_PASSWORD del entorno (.env) si están
 * presentes; si no, pregunta interactivamente. Idempotente: si el email
 * ya existe, actualiza su rol a Administrador en vez de duplicar.
 */
class CrearAdministradorCommand extends Command
{
    protected $signature = 'sgsi:crear-admin
        {--nombre= : Nombre completo (si se omite, usa ADMIN_NOMBRE del .env o pregunta)}
        {--email= : Correo institucional (si se omite, usa ADMIN_EMAIL del .env o pregunta)}
        {--password= : Contraseña (si se omite, usa ADMIN_PASSWORD del .env o pregunta)}';

    protected $description = 'Crea (o promueve a) el usuario Administrador inicial del SGSI';

    public function handle(): int
    {
        $nombre = $this->option('nombre') ?: env('ADMIN_NOMBRE') ?: $this->ask('Nombre completo del administrador');
        $email = $this->option('email') ?: env('ADMIN_EMAIL') ?: $this->ask('Correo institucional');
        $password = $this->option('password') ?: env('ADMIN_PASSWORD') ?: $this->secret('Contraseña (mínimo 12 caracteres)');

        $validador = Validator::make(
            compact('nombre', 'email', 'password'),
            ['nombre' => 'required|string|max:150', 'email' => 'required|email', 'password' => 'required|string|min:12']
        );

        if ($validador->fails()) {
            $this->error('Datos inválidos:');
            foreach ($validador->errors()->all() as $error) {
                $this->line("  - {$error}");
            }

            return self::FAILURE;
        }

        $rolAdmin = Rol::query()->where('nombre', 'Administrador')->first();

        if ($rolAdmin === null) {
            $this->error('El rol "Administrador" no existe. Ejecute primero: php artisan db:seed --class=RolesPermisosSeeder');

            return self::FAILURE;
        }

        $usuario = Usuario::query()->updateOrCreate(
            ['email' => $email],
            [
                'nombre' => $nombre,
                'password_hash' => Hash::make($password),
                'rol_id' => $rolAdmin->id,
                'estado' => 'activo',
            ]
        );

        $this->info("Administrador listo: {$usuario->email} (uuid {$usuario->uuid}).");

        return self::SUCCESS;
    }
}