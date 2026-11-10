<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Manejo centralizado de excepciones para toda la API /api/v1.
 * Ningún controlador captura excepciones manualmente: todo error de dominio,
 * validación o autorización termina aquí y sale con el MISMO formato JSON.
 */
class Handler extends ExceptionHandler
{
    protected $dontFlash = ['password', 'password_confirmation'];

    public function register(): void
    {
        $this->renderable(function (DomainException $e, Request $request) {
            return $this->apiError($e->errorCode(), $e->getMessage(), $e->httpStatus(), $e->details());
        });

        $this->renderable(function (ValidationException $e, Request $request) {
            return $this->apiError('VALIDACION_FALLIDA', 'Los datos enviados no son válidos.', 422, $e->errors());
        });

        $this->renderable(function (AuthenticationException $e, Request $request) {
            return $this->apiError('NO_AUTENTICADO', $e->getMessage() ?: 'Debe iniciar sesión para continuar.', 401);
        });

        $this->renderable(function (AuthorizationException $e, Request $request) {
            return $this->apiError('AUTORIZACION_INSUFICIENTE', 'No tiene permisos para esta acción.', 403);
        });

        $this->renderable(function (ModelNotFoundException $e, Request $request) {
            return $this->apiError('RECURSO_NO_ENCONTRADO', 'El recurso solicitado no existe.', 404);
        });

        // abort(404/403/...) y rutas inexistentes: se respeta el código HTTP y el mensaje
        // en lugar de convertirlos en un 500 genérico.
        $this->renderable(function (HttpExceptionInterface $e, Request $request) {
            $status = $e->getStatusCode();
            $codigo = match ($status) {
                404 => 'RECURSO_NO_ENCONTRADO',
                403 => 'AUTORIZACION_INSUFICIENTE',
                405 => 'METODO_NO_PERMITIDO',
                419 => 'SESION_EXPIRADA',
                429 => 'DEMASIADAS_PETICIONES',
                default => $status >= 500 ? 'ERROR_INTERNO' : 'PETICION_INVALIDA',
            };
            // El mensaje nativo del limitador viene en inglés ("Too Many Attempts.").
            $mensaje = ($status === 429 ? '' : $e->getMessage()) ?: match ($status) {
                404 => 'El recurso solicitado no existe.',
                419 => 'La sesión expiró. Recargue la página.',
                429 => 'Demasiadas peticiones. Intente en unos momentos.',
                default => 'No se pudo procesar la petición.',
            };

            return $this->apiError($codigo, $mensaje, $status);
        });

        $this->renderable(function (Throwable $e, Request $request) {
            Log::error('Error no controlado: ' . $e->getMessage(), [
                'excepcion' => get_class($e),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'ruta' => $request->path(),
            ]);

            if (app()->environment('production')) {
                return $this->apiError('ERROR_INTERNO', 'Ocurrió un error inesperado. Intente más tarde.', 500);
            }

            return null;
        });
    }

    private function apiError(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }
}