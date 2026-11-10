<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\AuditoriaActualizada;
use App\Events\HallazgoRegistrado;
use App\Events\NoConformidadCerrada;
use App\Exceptions\AccionCorrectivaVencidaException;
use App\Models\Auditoria;
use App\Models\Hallazgo;
use App\Models\NoConformidad;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AuditoriaService
{
    public function __construct(private readonly AuditoriaRepositoryInterface $auditorias)
    {
    }

    public function listar(int $porPagina, array $filtros): LengthAwarePaginator
    {
        return $this->auditorias->paginar($porPagina, $filtros);
    }

    public function planear(array $datos, ?int $usuarioId = null): Auditoria
    {
        return DB::transaction(function () use ($datos, $usuarioId) {
            $auditoria = $this->auditorias->crear($datos);
            event(new AuditoriaActualizada($auditoria, 'create', $usuarioId));

            return $auditoria;
        });
    }

    public function actualizar(Auditoria $auditoria, array $datos, ?int $usuarioId = null): Auditoria
    {
        return DB::transaction(function () use ($auditoria, $datos, $usuarioId) {
            $actualizada = $this->auditorias->actualizar($auditoria, $datos);
            event(new AuditoriaActualizada($actualizada, 'update', $usuarioId));

            return $actualizada;
        });
    }

    public function eliminar(Auditoria $auditoria): void
    {
        $this->auditorias->eliminar($auditoria);
    }

    public function registrarHallazgoConSeguimiento(Auditoria $auditoria, array $datosHallazgo, int $usuarioId): Hallazgo
    {
        return DB::transaction(function () use ($auditoria, $datosHallazgo, $usuarioId) {
            $hallazgo = $auditoria->hallazgos()->create([
                'severidad' => $datosHallazgo['severidad'],
                'descripcion' => $datosHallazgo['descripcion'],
            ]);

            event(new HallazgoRegistrado($hallazgo, $usuarioId));

            if (!empty($datosHallazgo['no_conformidad'])) {
                $noConformidad = $hallazgo->noConformidades()->create(['estado' => 'abierta']);

                if (!empty($datosHallazgo['no_conformidad']['accion_correctiva'])) {
                    $accion = $datosHallazgo['no_conformidad']['accion_correctiva'];
                    $noConformidad->accionesCorrectivas()->create([
                        'responsable_id' => $accion['responsable_id'],
                        'fecha_compromiso' => $accion['fecha_compromiso'],
                        'estado' => 'abierta',
                    ]);
                }
            }

            return $hallazgo;
        });
    }

    public function cerrarNoConformidad(NoConformidad $noConformidad, int $usuarioId): NoConformidad
    {
        $pendientes = $noConformidad->accionesCorrectivas()->whereNotIn('estado', ['cerrada'])->count();

        if ($pendientes > 0) {
            throw new AccionCorrectivaVencidaException($noConformidad->id);
        }

        return DB::transaction(function () use ($noConformidad, $usuarioId) {
            $noConformidad->update(['estado' => 'cerrada']);
            event(new NoConformidadCerrada($noConformidad, $usuarioId));

            return $noConformidad->refresh();
        });
    }
}