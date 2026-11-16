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
use App\Services\GestionCuentasService;
use App\Support\Bitacora;
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
            ->paginate(\App\Support\Paginacion::porPagina($request, 15));

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

        Bitacora::registrar('usuarios', $usuario->id, $request->user()->id, 'create', [
            'evento' => 'usuario_creado',
            'nombre' => $usuario->nombre,
            'email' => $usuario->email,
            'rol_id' => $usuario->rol_id,
        ]);

        return ApiResponse::creado(new UsuarioResource($usuario->load('rol.permisos')));
    }

    #[OA\Put(path: '/api/v1/usuarios/{usuario}', tags: ['Usuarios'], summary: 'Edita nombre, correo, rol o contraseña de un usuario')]
    public function update(UpdateUsuarioRequest $request, Usuario $usuario): JsonResponse
    {
        $datos = $request->validated();

        if (!empty($datos['password'])) {
            $datos['password_hash'] = Hash::make($datos['password']);
        }
        $passwordCambiada = isset($datos['password_hash']);
        unset($datos['password']);

        $antes = $usuario->only(['nombre', 'email', 'rol_id']);

        // Nunca dejar el sistema sin un Administrador activo (bloqueo total de la administración).
        if (isset($datos['rol_id']) && (int) $datos['rol_id'] !== (int) $usuario->rol_id
            && \App\Policies\UsuarioPolicy::esAdministrador($usuario) && GestionCuentasService::administradoresActivos() <= 1) {
            throw \Illuminate\Validation\ValidationException::withMessages(['rol_id' => 'No se puede quitar el rol al único Administrador activo.']);
        }

        $usuario->update($datos);

        // Contraseña restablecida por un administrador: se cierran las sesiones
        // abiertas de esa cuenta (salvo la propia, si se edita a sí mismo).
        if ($passwordCambiada && !$usuario->is($request->user())) {
            app(GestionCuentasService::class)->cerrarTodasLasSesiones($usuario);
        }

        $cambios = Bitacora::diferencias($antes, $usuario->only(['nombre', 'email', 'rol_id']));
        if ($passwordCambiada) {
            $cambios['contrasena_restablecida_por_administrador'] = true;
        }
        if ($cambios !== []) {
            Bitacora::registrar('usuarios', $usuario->id, $request->user()->id, 'update', ['evento' => 'usuario_editado'] + $cambios);
        }

        return ApiResponse::item(new UsuarioResource($usuario->fresh()->load('rol.permisos')));
    }

    #[OA\Patch(
        path: '/api/v1/usuarios/{usuario}/suspender',
        tags: ['Usuarios'],
        summary: 'Alterna el estado activo/suspendido de un usuario (nunca a sí mismo). Acepta `motivo` opcional al suspender.'
    )]
    public function suspender(Request $request, Usuario $usuario, GestionCuentasService $cuentas): JsonResponse
    {
        $this->authorize('suspender', $usuario);
        $datos = $request->validate(['motivo' => ['nullable', 'string', 'max:255']], [
            'motivo.max' => 'El motivo no puede superar 255 caracteres.',
        ]);

        $resultado = $usuario->estado === EstadoUsuario::Activo
            ? $cuentas->suspender($usuario, $request->user(), $datos['motivo'] ?? null)
            : $cuentas->reactivar($usuario, $request->user());

        return ApiResponse::item(new UsuarioResource($resultado->load('rol.permisos')));
    }
}
