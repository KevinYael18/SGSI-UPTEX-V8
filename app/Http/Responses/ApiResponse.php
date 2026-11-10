<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Envoltura uniforme de respuestas exitosas para toda /api/v1.
 * Evita que cada Controller decida su propio formato de salida.
 */
final class ApiResponse
{
    public static function item(JsonResource $resource, int $status = 200): JsonResponse
    {
        return $resource->response()->setStatusCode($status);
    }

    public static function coleccion(ResourceCollection $resource): JsonResponse
    {
        return $resource->response();
    }

    public static function mensaje(string $mensaje, int $status = 200): JsonResponse
    {
        return response()->json(['mensaje' => $mensaje], $status);
    }

    public static function creado(JsonResource $resource): JsonResponse
    {
        return self::item($resource, 201);
    }

    public static function sinContenido(): JsonResponse
    {
        return response()->json(null, 204);
    }
}