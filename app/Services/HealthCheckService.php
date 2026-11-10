<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HealthCheckLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Ajustado a la arquitectura de 2 contenedores (App + MySQL, sin Redis):
 * ya no verifica Redis. "Cola" se valida contra el driver 'database'
 * (tabla `jobs`), coherente con QUEUE_CONNECTION=database del .env.
 */
class HealthCheckService
{
    public function ejecutar(): array
    {
        $estadoBd = $this->verificar(fn () => DB::connection()->getPdo() !== null);
        $estadoCola = $this->verificar(fn () => DB::table('jobs')->count() >= 0);
        $estadoStorage = $this->verificar(fn () => Storage::disk(config('filesystems.default'))->exists('/'));

        $resultado = [
            'estado_bd' => $estadoBd,
            'estado_cola' => $estadoCola,
            'estado_storage' => $estadoStorage,
        ];

        HealthCheckLog::query()->create([...$resultado, 'created_at' => now()]);

        return $resultado;
    }

    private function verificar(\Closure $chequeo): string
    {
        try {
            return $chequeo() ? 'ok' : 'error';
        } catch (Throwable) {
            return 'error';
        }
    }
}