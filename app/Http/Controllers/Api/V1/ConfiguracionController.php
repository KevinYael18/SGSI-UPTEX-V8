<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateConfiguracionRequest;
use App\Http\Resources\ConfiguracionResource;
use App\Http\Responses\ApiResponse;
use App\Repositories\Contracts\ConfiguracionRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ConfiguracionController extends Controller
{
    public function __construct(private readonly ConfiguracionRepositoryInterface $configuracion)
    {
    }

    #[OA\Get(path: '/api/v1/configuracion', tags: ['Configuracion'], summary: 'Lista toda la configuración general del sistema (solo Administrador)')]
    public function index(): JsonResponse
    {
        $this->authorize('gestionar-configuracion');

        return ApiResponse::coleccion(ConfiguracionResource::collection($this->configuracion->todos()));
    }

    #[OA\Put(path: '/api/v1/configuracion', tags: ['Configuracion'], summary: 'Actualiza uno o varios valores de configuración en una transacción')]
    public function update(UpdateConfiguracionRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('valores') as $clave => $valor) {
                $this->configuracion->establecer($clave, $valor);
            }
        });

        return ApiResponse::mensaje('Configuración actualizada.');
    }
}