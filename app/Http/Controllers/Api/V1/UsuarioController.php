<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Http\Responses\ApiResponse;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class UsuarioController extends Controller
{
    #[OA\Get(path: '/api/v1/usuarios', tags: ['Usuarios'], summary: 'Lista todos los usuarios del sistema')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Usuario::class);

        $usuarios = Usuario::query()
            ->with('rol')
            ->orderBy('nombre')
            ->paginate((int) $request->integer('por_pagina', 15));

        return ApiResponse::coleccion(UsuarioResource::collection($usuarios));
    }

    #[OA\Post(path: '/api/v1/usuarios', tags: ['Usuarios'], summary: 'Crea un usuario nuevo con su rol asignado')]
    public function store(StoreUsuarioRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['password_hash'] = Hash::make($datos['password']);
        $datos['estado'] = EstadoUsuario::Activo->value;
        unset($datos['password']);

        $usuario = Usuario::query()->create($datos);

        return ApiResponse::creado(new UsuarioResource($usuario->load('rol.permisos')));
    }

    #[OA\Put(path: '/api/v1/usuarios/{usuario}', tags: ['Usuarios'], summary: 'Edita nombre, correo, rol o contraseña de un usuario')]
    public function update(UpdateUsuarioRequest $request, Usuario $usuario): JsonResponse
    {
        $datos = $request->validated();

        if (!empty($datos['password'])) {
            $datos['password_hash'] = Hash::make($datos['password']);
        }
        unset($datos['password']);

        $usuario->update($datos);

        return ApiResponse::item(new UsuarioResource($usuario->fresh()->load('rol.permisos')));
    }

    #[OA\Patch(
        path: '/api/v1/usuarios/{usuario}/suspender',
        tags: ['Usuarios'],
        summary: 'Alterna el estado activo/suspendido de un usuario (nunca a sí mismo)'
    )]
    public function suspender(Usuario $usuario): JsonResponse
    {
        $this->authorize('suspender', $usuario);

        $nuevoEstado = $usuario->estado === EstadoUsuario::Activo ? EstadoUsuario::Suspendido : EstadoUsuario::Activo;
        $usuario->update(['estado' => $nuevoEstado->value]);

        return ApiResponse::item(new UsuarioResource($usuario->fresh()->load('rol.permisos')));
    }
}