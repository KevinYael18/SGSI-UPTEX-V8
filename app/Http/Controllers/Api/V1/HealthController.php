<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class HealthController extends Controller
{
    public function __construct(private readonly HealthCheckService $service)
    {
    }

    #[OA\Get(
        path: '/api/health',
        tags: ['Health'],
        summary: 'Estado de BD, cola y almacenamiento — usado por Docker healthcheck y el dashboard'
    )]
    public function __invoke(): JsonResponse
    {
        $resultado = $this->service->ejecutar();
        $saludable = !in_array('error', $resultado, true);

        return response()->json($resultado, $saludable ? 200 : 503);
    }
}