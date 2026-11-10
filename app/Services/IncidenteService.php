<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoIncidente;
use App\Events\IncidenteActualizado;
use App\Events\IncidenteCerrado;
use App\Events\IncidenteReportado;
use App\Exceptions\TransicionEstadoInvalidaException;
use App\Models\Incidente;
use App\Repositories\Contracts\IncidenteRepositoryInterface;
use App\Support\EvidenciaUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class IncidenteService
{
    public function __construct(
        private readonly IncidenteRepositoryInterface $incidentes,
        private readonly EvidenciaUploader $evidenciaUploader,
    ) {
    }

    public function listar(int $porPagina, array $filtros): LengthAwarePaginator
    {
        return $this->incidentes->paginar($porPagina, $filtros);
    }

    public function reportar(array $datos, ?UploadedFile $evidencia, int $usuarioId): Incidente
    {
        $datos['reportado_por'] = $usuarioId;
        $datos['estado'] = EstadoIncidente::Abierto->value;

        return DB::transaction(function () use ($datos, $evidencia, $usuarioId) {
            $incidente = $this->incidentes->crear($datos);

            if ($evidencia !== null) {
                $this->evidenciaUploader->subir($evidencia, $incidente, $usuarioId, diasRetencion: 365);
            }

            event(new IncidenteReportado($incidente, $usuarioId));

            return $incidente;
        });
    }

    public function cambiarEstado(Incidente $incidente, EstadoIncidente $nuevoEstado, ?int $usuarioId = null): Incidente
    {
        if (!in_array($nuevoEstado, $incidente->estado->transicionesPermitidas(), true)) {
            throw new TransicionEstadoInvalidaException('incidente', $incidente->estado->value, $nuevoEstado->value);
        }

        return DB::transaction(function () use ($incidente, $nuevoEstado, $usuarioId) {
            $actualizado = $this->incidentes->actualizar($incidente, ['estado' => $nuevoEstado->value]);

            if ($nuevoEstado === EstadoIncidente::Cerrado) {
                event(new IncidenteCerrado($actualizado, $usuarioId));
            }

            return $actualizado;
        });
    }

    public function actualizar(Incidente $incidente, array $datos, ?int $usuarioId = null): Incidente
    {
        return DB::transaction(function () use ($incidente, $datos, $usuarioId) {
            $actualizado = $this->incidentes->actualizar($incidente, $datos);
            event(new IncidenteActualizado($actualizado, $datos, $usuarioId));

            return $actualizado;
        });
    }

    public function eliminar(Incidente $incidente): void
    {
        $this->incidentes->eliminar($incidente);
    }
}