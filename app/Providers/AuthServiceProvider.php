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
        \App\Models\Computadora::class => \App\Policies\ComputadoraPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // API para SPA: una petición sin sesión NUNCA se redirige a una ruta
        // «login» (no existe en el backend). Antes, sin cabecera Accept JSON,
        // el middleware intentaba route('login') y respondía 500 en vez de 401.
        \Illuminate\Auth\Middleware\Authenticate::redirectUsing(fn () => null);

        // Exportaciones Excel: el texto que empieza con «=» se guarda como TEXTO,
        // nunca como fórmula (CWE-1236, inyección de fórmulas/CSV). Los números
        // siguen siendo numéricos y las celdas vacías, vacías.
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(
            (new \PhpOffice\PhpSpreadsheet\Cell\StringValueBinder())
                ->setNumericConversion(false)
                ->setBooleanConversion(false)
                ->setNullConversion(false)
                ->setFormulaConversion(true)
        );

        Gate::define('ver-dashboard', [DashboardPolicy::class, 'ver']);
        Gate::define('gestionar-configuracion', [ConfiguracionPolicy::class, 'gestionar']);
        Gate::define('gestionar-roles', fn (Usuario $usuario) => false);

        // Inventario tecnológico: cada computadora es un Activo, así que se
        // reutilizan los permisos del módulo de Activos (no se crean roles nuevos).
        Gate::define('inventario.ver', fn (Usuario $u) => $u->tienePermiso('activos.ver'));
        Gate::define('inventario.gestionar', fn (Usuario $u) => $u->tienePermiso('activos.crear') || $u->tienePermiso('activos.editar'));
        Gate::define('inventario.eliminar', fn (Usuario $u) => $u->tienePermiso('activos.eliminar'));

        // SGSI Intelligence Assistant (permisos ia.*; Administrador vía Gate::before).
        Gate::define('ia.usar', fn (Usuario $u) => $u->tienePermiso('ia.usar'));
        Gate::define('ia.conocimiento', fn (Usuario $u) => $u->tienePermiso('ia.conocimiento'));
        Gate::define('ia.administrar', fn (Usuario $u) => $u->tienePermiso('ia.administrar'));

        Gate::before(function (Usuario $usuario, string $ability) {
            return $usuario->rol->nombre === 'Administrador' ? true : null;
        });

        // Módulo «Mi perfil»: limitadores con NOMBRE y clave propia por acción y
        // usuario. (`throttle:N,1` sin nombre comparte un solo contador entre
        // todas las rutas del mismo usuario; aquí cada acción cuenta aparte.)
        // Asistente de IA: 10 sugerencias por minuto por usuario (protege la cuota de Gemini).
        RateLimiter::for('ia-sugerencias', fn (Request $request) => Limit::perMinute(10)
            ->by('ia-sugerencias|' . ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        // SGSI Intelligence Assistant: conversación y carga de la base de conocimiento.
        RateLimiter::for('ia-chat', fn (Request $request) => Limit::perMinute(15)
            ->by('ia-chat|' . ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('ia-conocimiento', fn (Request $request) => Limit::perMinute(6)
            ->by('ia-conocimiento|' . ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        foreach (['perfil-datos' => 20, 'perfil-foto' => 10, 'perfil-password' => 6] as $nombre => $porMinuto) {
            RateLimiter::for($nombre, fn (Request $request) => Limit::perMinute($porMinuto)
                ->by($nombre . '|' . ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        }

        // Recuperación de contraseña: contadores independientes por acción e IP
        // (antes compartían el contador anónimo de `throttle:5,1` con el login).
        // El broker además limita 1 correo por cuenta cada AUTH_PASSWORD_RESET_THROTTLE s.
        foreach (['recuperacion-solicitar' => 5, 'recuperacion-validar' => 20, 'recuperacion-restablecer' => 10] as $nombre => $porMinuto) {
            RateLimiter::for($nombre, fn (Request $request) => Limit::perMinute($porMinuto)->by($nombre . '|' . $request->ip()));
        }

        // Solo aplica a la notificación nativa de Laravel; SGSI usa
        // RestablecerPasswordNotification (ver Usuario::sendPasswordResetNotification).
        ResetPassword::createUrlUsing(function ($usuario, string $token) {
            return config('app.url') . '/restablecer-password?token=' . $token . '&email=' . urlencode($usuario->email);
        });
    }
}