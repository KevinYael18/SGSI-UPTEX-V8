<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Support\RegistroTimeline;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TimelineService
{
    public function __construct(private readonly ActivityLogRepositoryInterface $activityLog)
    {
    }

    public function paraEntidad(string $tipo, string $identificador): Collection
    {
        $modelo = RegistroTimeline::resolverModelo($tipo, $identificador);

        if ($modelo === null) {
            throw new NotFoundHttpException("No existe {$tipo}/{$identificador}.");
        }

        return $this->activityLog->historialDe($modelo->getTable(), $modelo->getKey())
            ->map(fn ($log) => [
                'accion' => $log->accion,
                'usuario' => $log->usuario?->nombre,
                'cambios' => $log->cambios,
                'fecha' => $log->created_at->toIso8601String(),
            ]);
    }
}