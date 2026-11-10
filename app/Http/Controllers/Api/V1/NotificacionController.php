<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarcarLeidaRequest;
use App\Http\Resources\NotificacionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Notificacion;
use App\Services\NotificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NotificacionController extends Controller
{
    public function __construct(private readonly NotificacionService $service)
    {
    }

    #[OA\Get(path: '/api/v1/notificaciones', tags: ['Notificaciones'], summary: 'Lista las notificaciones del usuario autenticado')]
    public function index(Request $request): JsonResponse
    {
        $notificaciones = $this->service->listar(
            $request->user()->id,
            (int) $request->integer('por_pagina', 15),
            $request->has('leida') ? ['leida' => $request->boolean('leida')] : []
        );

        return ApiResponse::coleccion(NotificacionResource::collection($notificaciones));
    }

    #[OA\Get(path: '/api/v1/notificaciones/no-leidas/conteo', tags: ['Notificaciones'], summary: 'Contador para el ícono de campana del SPA')]
    public function contarNoLeidas(Request $request): JsonResponse
    {
        return response()->json(['data' => ['no_leidas' => $this->service->contarNoLeidas($request->user()->id)]]);
    }

    #[OA\Patch(path: '/api/v1/notificaciones/{notificacion}/leida', tags: ['Notificaciones'], summary: 'Marca una notificación propia como leída')]
    public function marcarLeida(MarcarLeidaRequest $request, Notificacion $notificacion): JsonResponse
    {
        return ApiResponse::item(new NotificacionResource($this->service->marcarLeida($notificacion)));
    }

    #[OA\Post(path: '/api/v1/notificaciones/marcar-todas-leidas', tags: ['Notificaciones'], summary: 'Marca todas las notificaciones propias como leídas')]
    public function marcarTodasLeidas(Request $request): JsonResponse
    {
        $total = $this->service->marcarTodasLeidas($request->user()->id);

        return ApiResponse::mensaje("{$total} notificación(es) marcadas como leídas.");
    }
}