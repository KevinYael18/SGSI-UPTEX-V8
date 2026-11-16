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

        // Se añaden edificio, piso y tipo de espacio (campos nuevos y opcionales);
        // `descripcion` sirve para mostrar la ubicación completa en los selectores.
        return response()->json([
            'data' => $query->with('edificioRelacion')->get(['id', 'nombre', 'departamento_id', 'edificio_id', 'edificio', 'piso', 'tipo_espacio'])
                ->map(fn ($u) => $u->only(['id', 'nombre', 'departamento_id', 'edificio_id', 'piso', 'tipo_espacio']) + ['descripcion' => $u->descripcion()]),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/catalogos/usuarios',
        tags: ['Catalogos'],
        summary: 'Lista de usuarios activos para selectores (responsable de activo, auditor, etc.)'
    )]
    public function usuarios(Request $request): JsonResponse
    {
        $user = $request->user();

        // El rol Consulta (solo lectura) no necesita los correos institucionales:
        // recibe únicamente id y nombre. Los demás roles los usan en los selectores
        // de responsable/auditor.
        $columnas = $user->rol->nombre === 'Consulta'
            ? ['id', 'nombre']
            : ['id', 'nombre', 'email'];

        return response()->json([
            'data' => Usuario::query()
                ->where('estado', 'activo')
                ->orderBy('nombre')
                ->get($columnas),
        ]);
    }

    #[OA\Get(path: '/api/v1/catalogos/activos', tags: ['Catalogos'], summary: 'Lista ligera de activos para selectores (sin paginar)')]
    public function activos(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Activo::query()->visiblePara($request->user())->orderBy('nombre')->get(['id', 'uuid', 'nombre']),
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