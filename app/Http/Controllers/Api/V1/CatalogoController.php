<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activo;
use App\Models\Amenaza;
use App\Models\Departamento;
use App\Models\Ubicacion;
use App\Models\Usuario;
use App\Models\Vulnerabilidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Endpoints de solo lectura para poblar selectores del frontend (catálogos
 * maestros: departamentos, ubicaciones, usuarios). No tienen Policy propia
 * — cualquier usuario autenticado puede leerlos, ya que son listas de
 * referencia sin datos sensibles (nombre/email, nunca password_hash u
 * otra información privada).
 */
class CatalogoController extends Controller
{
    #[OA\Get(path: '/api/v1/catalogos/departamentos', tags: ['Catalogos'], summary: 'Lista de departamentos para selectores')]
    public function departamentos(): JsonResponse
    {
        return response()->json([
            'data' => Departamento::query()->orderBy('nombre')->get(['id', 'clave', 'nombre']),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/catalogos/ubicaciones',
        tags: ['Catalogos'],
        summary: 'Lista de ubicaciones, filtrable por departamento (selector en cascada)'
    )]
    public function ubicaciones(Request $request): JsonResponse
    {
        $query = Ubicacion::query()->orderBy('nombre');

        if ($request->filled('departamento_id')) {
            $query->where('departamento_id', $request->integer('departamento_id'));
        }

        return response()->json([
            'data' => $query->get(['id', 'nombre', 'departamento_id']),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/catalogos/usuarios',
        tags: ['Catalogos'],
        summary: 'Lista de usuarios activos para selectores (responsable de activo, auditor, etc.)'
    )]
    public function usuarios(): JsonResponse
    {
        return response()->json([
            'data' => Usuario::query()
                ->where('estado', 'activo')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'email']),
        ]);
    }

    #[OA\Get(path: '/api/v1/catalogos/activos', tags: ['Catalogos'], summary: 'Lista ligera de activos para selectores (sin paginar)')]
    public function activos(): JsonResponse
    {
        return response()->json([
            'data' => Activo::query()->orderBy('nombre')->get(['id', 'uuid', 'nombre']),
        ]);
    }

    #[OA\Get(path: '/api/v1/catalogos/amenazas', tags: ['Catalogos'], summary: 'Lista de amenazas para selectores')]
    public function amenazas(): JsonResponse
    {
        return response()->json([
            'data' => Amenaza::query()->orderBy('nombre')->get(['id', 'nombre', 'categoria']),
        ]);
    }

    #[OA\Get(path: '/api/v1/catalogos/vulnerabilidades', tags: ['Catalogos'], summary: 'Lista de vulnerabilidades para selectores')]
    public function vulnerabilidades(): JsonResponse
    {
        return response()->json([
            'data' => Vulnerabilidad::query()->orderBy('nombre')->get(['id', 'nombre', 'categoria']),
        ]);
    }
}