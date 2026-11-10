<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\ControlIsoActualizado;
use App\Events\Contracts\GeneraNotificacion;
use App\Events\Contracts\RegistraBitacora;
use App\Events\DocumentoPublicado;
use App\Events\IntentoFallidoLogin;
use App\Events\RiesgoRecalculado;
use App\Events\UsuarioAutenticado;
use App\Listeners\BloquearPorIntentos;
use App\Listeners\InvalidarCacheDashboard;
use App\Listeners\NotificarPorEventoDominio;
use App\Listeners\RegistrarActivityLog;
use App\Listeners\RegistrarActivityLogGenerico;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Dos contratos genéricos cubren transversalmente TODOS los módulos:
 *  - RegistraBitacora  -> RegistrarActivityLogGenerico (bitácora técnica)
 *  - GeneraNotificacion -> NotificarPorEventoDominio (Notifications BD+mail)
 * Un módulo nuevo que implemente cualquiera de los dos NO requiere tocar
 * este archivo. Solo se listan explícitamente los eventos que además
 * deben invalidar el cache del dashboard (regla puntual, no genérica), y
 * UsuarioAutenticado, que usa su propio listener porque NO implementa
 * RegistraBitacora (su bitácora es un caso especial: registra el login,
 * no un cambio de entidad de negocio con antes/después).
 */
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        RegistraBitacora::class => [
            RegistrarActivityLogGenerico::class,
        ],
        GeneraNotificacion::class => [
            NotificarPorEventoDominio::class,
        ],

        UsuarioAutenticado::class => [
            RegistrarActivityLog::class,
        ],
        IntentoFallidoLogin::class => [
            BloquearPorIntentos::class,
        ],

        RiesgoRecalculado::class => [
            InvalidarCacheDashboard::class,
        ],
        ControlIsoActualizado::class => [
            InvalidarCacheDashboard::class,
        ],
        DocumentoPublicado::class => [
            InvalidarCacheDashboard::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}