<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\AccionCorrectivaRepositoryInterface;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\ActivoRepositoryInterface;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\ComentarioRepositoryInterface;
use App\Repositories\Contracts\ConfiguracionRepositoryInterface;
use App\Repositories\Contracts\ControlIsoRepositoryInterface;
use App\Repositories\Contracts\DocumentoRepositoryInterface;
use App\Repositories\Contracts\IncidenteRepositoryInterface;
use App\Repositories\Contracts\MatrizRiesgoRepositoryInterface;
use App\Repositories\Contracts\NotificacionRepositoryInterface;
use App\Repositories\Contracts\RiesgoRepositoryInterface;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use App\Repositories\Eloquent\AccionCorrectivaRepository;
use App\Repositories\Eloquent\ActivityLogRepository;
use App\Repositories\Eloquent\ActivoRepository;
use App\Repositories\Eloquent\AuditoriaRepository;
use App\Repositories\Eloquent\ComentarioRepository;
use App\Repositories\Eloquent\ConfiguracionRepository;
use App\Repositories\Eloquent\ControlIsoRepository;
use App\Repositories\Eloquent\DocumentoRepository;
use App\Repositories\Eloquent\IncidenteRepository;
use App\Repositories\Eloquent\MatrizRiesgoRepository;
use App\Repositories\Eloquent\NotificacionRepository;
use App\Repositories\Eloquent\RiesgoRepository;
use App\Repositories\Eloquent\UsuarioRepository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Laravel 11+/12 no autodescubre app/Exceptions/Handler.php (el
        // esqueleto configura excepciones en bootstrap/app.php) — se liga
        // aquí explícitamente para que el formato JSON uniforme de errores
        // (ver App\Exceptions\Handler) realmente entre en efecto en toda
        // /api/v1, sin tener que editar bootstrap/app.php con parches frágiles.
        $this->app->singleton(ExceptionHandler::class, \App\Exceptions\Handler::class);

        $this->app->bind(UsuarioRepositoryInterface::class, UsuarioRepository::class);
        $this->app->bind(ActivoRepositoryInterface::class, ActivoRepository::class);
        $this->app->bind(RiesgoRepositoryInterface::class, RiesgoRepository::class);
        $this->app->bind(MatrizRiesgoRepositoryInterface::class, MatrizRiesgoRepository::class);
        $this->app->bind(IncidenteRepositoryInterface::class, IncidenteRepository::class);
        $this->app->bind(AuditoriaRepositoryInterface::class, AuditoriaRepository::class);
        $this->app->bind(AccionCorrectivaRepositoryInterface::class, AccionCorrectivaRepository::class);
        $this->app->bind(ComentarioRepositoryInterface::class, ComentarioRepository::class);
        $this->app->bind(DocumentoRepositoryInterface::class, DocumentoRepository::class);
        $this->app->bind(ControlIsoRepositoryInterface::class, ControlIsoRepository::class);
        $this->app->bind(NotificacionRepositoryInterface::class, NotificacionRepository::class);
        $this->app->bind(ConfiguracionRepositoryInterface::class, ConfiguracionRepository::class);
        $this->app->bind(ActivityLogRepositoryInterface::class, ActivityLogRepository::class);
    }
}