<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\CacheVersionado;

/**
 * Escucha eventos de Activos/Riesgos/Controles ISO que afectan los KPIs
 * del dashboard ejecutivo, e invalida el cache (Ronda 2, mejora #4) sin
 * depender de tags de Redis — compatible con el driver 'database' del
 * contenedor único de aplicación.
 */
class InvalidarCacheDashboard
{
    public function handle(object $event): void
    {
        CacheVersionado::invalidar('dashboard-kpis');
    }
}
