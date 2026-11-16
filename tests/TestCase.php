<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Salvaguarda: las pruebas usan RefreshDatabase. Si la configuración
        // está cacheada (config:cache en el contenedor), `php artisan test`
        // apuntaría a la base REAL e intentaría reconstruirla. Se aborta antes
        // de tocar cualquier dato si la base no es de pruebas.
        $conexion = $app['config']->get('database.default');
        $base = (string) $app['config']->get("database.connections.{$conexion}.database");
        if ($conexion !== 'sqlite' && !str_ends_with($base, '_testing')) {
            throw new \RuntimeException(
                "Pruebas abortadas: la base «{$base}» no es de pruebas (debe terminar en _testing). "
                . 'Ejecute `php artisan config:clear` o use DB_DATABASE=sgsi_uptex_testing.'
            );
        }

        return $app;
    }
}
