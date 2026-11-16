<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Evidencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve el archivo real de una evidencia. La autorización se delega a la
 * Policy de la entidad DUEÑA de la evidencia (Incidente, Auditoría, etc.)
 * vía la relación polimórfica `evidenciable` — no existe una "EvidenciaPolicy"
 * propia porque el criterio real siempre es "¿puede este usuario ver el
 * incidente/auditoría al que pertenece esta evidencia?", nunca la evidencia
 * de forma aislada.
 *
 * Por defecto se sirve `inline` para que el navegador la muestre (imagen,
 * PDF o video). Con `?descargar=1` se fuerza la descarga como archivo.
 */
class EvidenciaController extends Controller
{
    #[OA\Get(
        path: '/api/v1/evidencias/{evidencia}/descargar',
        tags: ['Evidencias'],
        summary: 'Muestra (inline) o descarga (?descargar=1) el archivo de una evidencia, autorizado contra su entidad dueña'
    )]
    public function descargar(Request $request, Evidencia $evidencia): BinaryFileResponse|StreamedResponse
    {
        Gate::authorize('view', $evidencia->evidenciable);

        $disco = Storage::disk(config('filesystems.default'));

        if (!$disco->exists($evidencia->path_storage)) {
            abort(404, 'El archivo de esta evidencia ya no está disponible en el almacenamiento.');
        }

        $nombre = "evidencia-{$evidencia->uuid}." . str($evidencia->mime_type)->after('/');
        $disposicion = $request->boolean('descargar')
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;
        $cabeceras = [
            'Content-Type' => $evidencia->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ];

        // Disco local: BinaryFileResponse soporta peticiones por rangos (Range),
        // necesarias para que el <video> se pueda reproducir y adelantar (Safari las exige).
        if (config('filesystems.default') === 'local') {
            return response()
                ->file($disco->path($evidencia->path_storage), $cabeceras)
                ->setContentDisposition($disposicion, $nombre);
        }

        // Disco remoto (s3/MinIO): se transmite el contenido.
        return $disco->response($evidencia->path_storage, $nombre, $cabeceras, $disposicion);
    }

    #[OA\Get(path: '/api/v1/evidencias/{evidencia}/verificar', tags: ['Incidentes'], summary: 'Recalcula el SHA-256 del archivo almacenado y lo compara con el registrado')]
    public function verificar(Request $request, Evidencia $evidencia): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('view', $evidencia->evidenciable);

        $disco = Storage::disk(config('filesystems.default'));
        $existe = $disco->exists($evidencia->path_storage);
        $hashActual = $existe ? hash('sha256', (string) $disco->get($evidencia->path_storage)) : null;
        $integra = $existe && hash_equals((string) $evidencia->hash_sha256, (string) $hashActual);

        \App\Support\Bitacora::registrar('evidencias', $evidencia->id, $request->user()->id, 'update', [
            'evento' => 'integridad_verificada',
            'resultado' => $integra ? 'integra' : ($existe ? 'hash_no_coincide' : 'archivo_no_disponible'),
        ]);

        return response()->json(['data' => [
            'integra' => $integra,
            'archivo_disponible' => $existe,
            'hash_registrado' => $evidencia->hash_sha256,
            'hash_actual' => $hashActual,
            'verificado_en' => now()->toIso8601String(),
        ]]);
    }
}
