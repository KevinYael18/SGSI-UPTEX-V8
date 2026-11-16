<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class RolController extends Controller
{
    #[OA\Get(path: '/api/v1/roles', tags: ['Roles'], summary: 'Lista todos los roles con sus permisos asignados')]
    public function index(): JsonResponse
    {
        Gate::authorize('gestionar-roles');

        $roles = Rol::query()->with('permisos')->orderBy('nombre')->get();

        return response()->json([
            'data' => $roles->map(fn ($rol) => [
                'id' => $rol->id,
                'nombre' => $rol->nombre,
                'descripcion' => $rol->descripcion,
                'permisos' => $rol->permisos->pluck('clave'),
            ]),
        ]);
    }

    #[OA\Get(path: '/api/v1/permisos', tags: ['Roles'], summary: 'Catálogo completo de permisos disponibles (para la matriz de edición)')]
    public function permisosDisponibles(): JsonResponse
    {
        Gate::authorize('gestionar-roles');

        return response()->json([
            'data' => Permiso::query()->orderBy('modulo')->orderBy('clave')->get(['id', 'clave', 'modulo']),
        ]);
    }

    #[OA\Put(path: '/api/v1/roles/{rol}', tags: ['Roles'], summary: 'Reemplaza el conjunto completo de permisos de un rol')]
    public function actualizarPermisos(Request $request, Rol $rol): JsonResponse
    {
        Gate::authorize('gestionar-roles');

        if ($rol->nombre === 'Administrador') {
            abort(422, 'El rol Administrador siempre tiene acceso total (Gate::before) y no se gestiona por permisos individuales.');
        }

        $request->validate(['permisos' => ['array'], 'permisos.*' => ['integer', 'exists:permisos,id']]);
        $antes = $rol->permisos()->pluck('clave')->sort()->values()->all();

        $rol->permisos()->sync($request->input('permisos', []));
        $despues = $rol->permisos()->pluck('clave')->sort()->values()->all();

        // Trazabilidad (ISO 27001 A.8.15): un cambio de permisos altera lo que
        // puede hacer cada usuario de ese rol.
        \App\Support\Bitacora::registrar('roles', $rol->id, $request->user()->id, 'update', [
            'evento' => 'permisos_rol_actualizados',
            'agregados' => array_values(array_diff($despues, $antes)),
            'retirados' => array_values(array_diff($antes, $despues)),
        ]);

        return response()->json(['data' => [
            'id' => $rol->id,
            'nombre' => $rol->nombre,
            'permisos' => $rol->permisos()->pluck('clave'),
        ]]);
    }
}