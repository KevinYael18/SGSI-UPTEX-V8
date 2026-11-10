<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActualizarPerfilRequest;
use App\Http\Requests\CambiarPasswordRequest;
use App\Http\Requests\SubirFotoPerfilRequest;
use App\Http\Resources\PerfilResource;
use App\Http\Responses\ApiResponse;
use App\Models\Usuario;
use App\Services\PerfilService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Módulo «Mi perfil». Todas las acciones se aplican EXCLUSIVAMENTE al usuario
 * autenticado ($request->user()); las rutas no reciben ningún identificador,
 * así que no es posible consultar ni modificar el perfil de otra persona.
 * La administración de otros usuarios sigue en UsuarioController, sin cambios.
 */
class PerfilController extends Controller
{
    public function __construct(private readonly PerfilService $perfil)
    {
    }

    #[OA\Get(path: '/api/v1/perfil', tags: ['Perfil'], summary: 'Perfil completo del usuario autenticado')]
    public function show(Request $request): JsonResponse
    {
        return $this->respuesta($request->user());
    }

    #[OA\Put(path: '/api/v1/perfil', tags: ['Perfil'], summary: 'Actualiza los datos personales editables del usuario autenticado (nombre)')]
    public function update(ActualizarPerfilRequest $request): JsonResponse
    {
        $usuario = $this->perfil->actualizarDatos($request->user(), $request->validated());

        return $this->respuesta($usuario);
    }

    #[OA\Post(path: '/api/v1/perfil/foto', tags: ['Perfil'], summary: 'Sube/reemplaza la fotografía de perfil (JPG, PNG o WEBP, máx. 2 MB; multipart campo "foto")')]
    public function subirFoto(SubirFotoPerfilRequest $request): JsonResponse
    {
        $usuario = $this->perfil->guardarFoto($request->user(), $request->file('foto'));

        return $this->respuesta($usuario);
    }

    #[OA\Delete(path: '/api/v1/perfil/foto', tags: ['Perfil'], summary: 'Elimina la fotografía de perfil (vuelve al avatar con iniciales)')]
    public function eliminarFoto(Request $request): JsonResponse
    {
        $usuario = $this->perfil->eliminarFoto($request->user());

        return $this->respuesta($usuario);
    }

    #[OA\Put(path: '/api/v1/perfil/password', tags: ['Perfil'], summary: 'Cambia la contraseña del usuario autenticado y cierra sus demás sesiones')]
    public function cambiarPassword(CambiarPasswordRequest $request): JsonResponse
    {
        $sesion = $request->hasSession() ? $request->session() : null;

        $cerradas = $this->perfil->cambiarPassword(
            $request->user(),
            $request->string('password')->toString(),
            $sesion?->getId()
        );

        // Nuevo ID para la sesión actual (mitiga fijación de sesión) sin cerrarla.
        $sesion?->migrate(true);

        $mensaje = 'Contraseña actualizada correctamente.';
        if ($cerradas > 0) {
            $mensaje .= $cerradas === 1
                ? ' Se cerró 1 sesión abierta en otro dispositivo.'
                : " Se cerraron {$cerradas} sesiones abiertas en otros dispositivos.";
        }

        return ApiResponse::mensaje($mensaje);
    }

    private function respuesta(Usuario $usuario): JsonResponse
    {
        $usuario->load(['rol.permisos', 'departamentosConAlcance'])
            ->loadCount(['activosResponsable', 'incidentesReportados']);

        return ApiResponse::item(new PerfilResource($usuario));
    }
}
