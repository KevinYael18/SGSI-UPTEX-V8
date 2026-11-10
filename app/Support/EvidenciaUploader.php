<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ArchivoNoPermitidoException;
use App\Exceptions\EvidenciaCorruptaException;
use App\Models\Evidencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * SRP + LSP: aísla la subida de archivos del resto de IncidenteService.
 * Depende del contrato Filesystem de Laravel (no de MinIO directamente),
 * así que el driver "s3" (MinIO) o "local" son intercambiables sin tocar
 * esta clase (ajuste aprobado en Fase 1.5 §5: MinIO opcional vía .env).
 */
final class EvidenciaUploader
{
    private const MIMES_PERMITIDOS = ['image/jpeg', 'image/png', 'application/pdf', 'video/mp4'];

    public function subir(UploadedFile $archivo, Model $evidenciable, ?int $usuarioId, ?int $diasRetencion = null): Evidencia
    {
        if (!in_array($archivo->getMimeType(), self::MIMES_PERMITIDOS, true)) {
            throw new ArchivoNoPermitidoException((string) $archivo->getMimeType());
        }

        $disco = config('filesystems.default'); // 's3' (MinIO) o 'local' según .env
        $path = Storage::disk($disco)->putFile('evidencias', $archivo);
        $hashOriginal = hash_file('sha256', $archivo->getRealPath());
        $hashAlmacenado = hash('sha256', Storage::disk($disco)->get($path));

        if ($hashOriginal !== $hashAlmacenado) {
            Storage::disk($disco)->delete($path);
            throw new EvidenciaCorruptaException();
        }

        return $evidenciable->evidencias()->create([
            'subido_por' => $usuarioId,
            'path_storage' => $path,
            'mime_type' => $archivo->getMimeType(),
            'hash_sha256' => $hashOriginal,
            'fecha_expiracion' => $diasRetencion ? now()->addDays($diasRetencion)->toDateString() : null,
        ]);
    }
}