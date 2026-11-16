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
        $valores = $request->validated('valores');

        // Solo se actualizan parámetros que ya existen (antes se podía crear
        // cualquier clave arbitraria) y el cambio queda en la bitácora.
        $existentes = \App\Models\ConfiguracionSistema::query()->whereIn('clave', array_keys($valores))->pluck('valor', 'clave');
        $desconocidas = array_diff(array_keys($valores), $existentes->keys()->all());
        if ($desconocidas !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages(['valores' => 'Parámetros de configuración no reconocidos: ' . implode(', ', array_slice($desconocidas, 0, 10))]);
        }

        DB::transaction(function () use ($valores, $existentes, $request) {
            foreach ($valores as $clave => $valor) {
                $this->configuracion->establecer($clave, $valor);
            }
            $cambios = \App\Support\Bitacora::diferencias($existentes->all(), $valores);
            if ($cambios !== []) {
                \App\Support\Bitacora::registrar('configuracion_sistema', 0, $request->user()->id, 'update', ['evento' => 'configuracion_actualizada'] + $cambios);
            }
        });

        return ApiResponse::mensaje('Configuración actualizada.');
    }
}