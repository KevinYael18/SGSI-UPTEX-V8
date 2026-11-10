<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AccionCorrectiva;
use App\Models\Activo;
use App\Models\Auditoria;
use App\Models\ControlIso;
use App\Models\Documento;
use App\Models\Incidente;
use App\Models\Notificacion;
use App\Models\Riesgo;
use App\Models\Usuario;
use App\Policies\AccionCorrectivaPolicy;
use App\Policies\ActivoPolicy;
use App\Policies\AuditoriaPolicy;
use App\Policies\ConfiguracionPolicy;
use App\Policies\ControlIsoPolicy;
use App\Policies\DashboardPolicy;
use App\Policies\DocumentoPolicy;
use App\Policies\IncidentePolicy;
use App\Policies\NotificacionPolicy;
use App\Policies\RiesgoPolicy;
use App\Policies\UsuarioPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Activo::class => ActivoPolicy::class,
        Riesgo::class => RiesgoPolicy::class,
        Incidente::class => IncidentePolicy::class,
        Usuario::class => UsuarioPolicy::class,
        Auditoria::class => AuditoriaPolicy::class,
        Documento::class => DocumentoPolicy::class,
        ControlIso::class => ControlIsoPolicy::class,
        Notificacion::class => NotificacionPolicy::class,
        AccionCorrectiva::class => AccionCorrectivaPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('ver-dashboard', [DashboardPolicy::class, 'ver']);
        Gate::define('gestionar-configuracion', [ConfiguracionPolicy::class, 'gestionar']);
        Gate::define('gestionar-roles', fn (Usuario $usuario) => false);

        Gate::before(function (Usuario $usuario, string $ability) {
            return $usuario->rol->nombre === 'Administrador' ? true : null;
        });

        // Módulo «Mi perfil»: limitadores con NOMBRE y clave propia por acción y
        // usuario. (`throttle:N,1` sin nombre comparte un solo contador entre
        // todas las rutas del mismo usuario; aquí cada acción cuenta aparte.)
        foreach (['perfil-datos' => 20, 'perfil-foto' => 10, 'perfil-password' => 6] as $nombre => $porMinuto) {
            RateLimiter::for($nombre, fn (Request $request) => Limit::perMinute($porMinuto)
                ->by($nombre . '|' . ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        }

        ResetPassword::createUrlUsing(function ($usuario, string $token) {
            return config('app.url') . '/restablecer-password?token=' . $token . '&email=' . urlencode($usuario->email);
        });
    }
}