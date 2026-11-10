<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoDocumento;
use App\Events\DocumentoActualizado;
use App\Events\DocumentoAprobado;
use App\Events\DocumentoCreado;
use App\Events\DocumentoPublicado;
use App\Events\NuevaVersionCreada;
use App\Exceptions\TransicionEstadoInvalidaException;
use App\Exceptions\VersionInmutableException;
use App\Models\Documento;
use App\Repositories\Contracts\DocumentoRepositoryInterface;
use App\Support\EvidenciaUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentoService
{
    public function __construct(private readonly DocumentoRepositoryInterface $documentos)
    {
    }

    public function listar(int $porPagina, array $filtros): LengthAwarePaginator
    {
        return $this->documentos->paginar($porPagina, $filtros);
    }

    public function crear(array $datos, UploadedFile $archivo, int $usuarioId): Documento
    {
        return DB::transaction(function () use ($datos, $archivo, $usuarioId) {
            $documento = $this->documentos->crear(['titulo' => $datos['titulo'], 'estado' => EstadoDocumento::Borrador->value, 'version_actual' => 1]);

            $path = Storage::disk(config('filesystems.default'))->putFile('documentos', $archivo);

            $documento->versiones()->create([
                'creado_por' => $usuarioId,
                'numero_version' => 1,
                'path_storage' => $path,
                'fecha_expiracion' => $datos['dias_vigencia'] ?? null
                    ? now()->addDays((int) $datos['dias_vigencia'])->toDateString()
                    : null,
            ]);

            event(new DocumentoCreado($documento, $usuarioId));

            return $documento;
        });
    }

    public function nuevaVersion(Documento $documento, UploadedFile $archivo, int $usuarioId, ?int $diasVigencia = null): Documento
    {
        if ($documento->estado === EstadoDocumento::Publicado) {
            throw new VersionInmutableException($documento->id);
        }

        return DB::transaction(function () use ($documento, $archivo, $usuarioId, $diasVigencia) {
            $path = Storage::disk(config('filesystems.default'))->putFile('documentos', $archivo);
            $nuevoNumero = $documento->version_actual + 1;

            $version = $documento->versiones()->create([
                'creado_por' => $usuarioId,
                'numero_version' => $nuevoNumero,
                'path_storage' => $path,
                'fecha_expiracion' => $diasVigencia ? now()->addDays($diasVigencia)->toDateString() : null,
            ]);

            $documento->update(['version_actual' => $nuevoNumero, 'estado' => EstadoDocumento::EnRevision->value]);

            event(new NuevaVersionCreada($version, $usuarioId));

            return $documento->refresh();
        });
    }

    public function cambiarEstado(Documento $documento, EstadoDocumento $nuevoEstado, int $usuarioId): Documento
    {
        if (!in_array($nuevoEstado, $documento->estado->transicionesPermitidas(), true)) {
            throw new TransicionEstadoInvalidaException('documento', $documento->estado->value, $nuevoEstado->value);
        }

        if ($nuevoEstado === EstadoDocumento::Aprobado) {
            $autorVersion = $documento->versionActual()?->creado_por;

            if ($autorVersion === $usuarioId) {
                throw new \App\Exceptions\AutorizacionInsuficienteException(
                    'aprobar un documento que usted mismo subió (segregación de funciones, control A.5.3)'
                );
            }
        }

        return DB::transaction(function () use ($documento, $nuevoEstado, $usuarioId) {
            $datos = ['estado' => $nuevoEstado->value];

            if ($nuevoEstado === EstadoDocumento::Publicado) {
                $datos['publicado_por'] = $usuarioId;
                $datos['publicado_en'] = now();
            }

            $documento->update($datos);
            $documento->refresh();

            if ($nuevoEstado === EstadoDocumento::Aprobado) {
                $documento->versionActual()?->update(['aprobado_por' => $usuarioId]);
            }

            match ($nuevoEstado) {
                EstadoDocumento::Aprobado => event(new DocumentoAprobado($documento, $usuarioId)),
                EstadoDocumento::Publicado => event(new DocumentoPublicado($documento, $usuarioId)),
                default => null,
            };

            return $documento;
        });
    }

    public function actualizarTitulo(Documento $documento, string $titulo, ?int $usuarioId = null): Documento
    {
        return DB::transaction(function () use ($documento, $titulo, $usuarioId) {
            $documento->update(['titulo' => $titulo]);
            event(new DocumentoActualizado($documento, $usuarioId));

            return $documento;
        });
    }

    public function eliminar(Documento $documento): void
    {
        $this->documentos->eliminar($documento);
    }
}