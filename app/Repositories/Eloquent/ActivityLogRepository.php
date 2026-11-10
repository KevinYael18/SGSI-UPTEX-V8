<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ActivityLog;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function historialDe(string $entidad, int $entidadId): Collection
    {
        return ActivityLog::query()
            ->where('entidad', $entidad)
            ->where('entidad_id', $entidadId)
            ->with('usuario:id,nombre')
            ->latest()
            ->get();
    }
}