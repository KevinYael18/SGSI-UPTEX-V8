<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activo;
use App\Models\ExportacionLog;
use App\Models\Riesgo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class ReporteService
{
    public function activosPdf(int $usuarioId, array $filtros = []): string
    {
        $activos = Activo::query()
            ->with(['departamento', 'responsable'])
            ->when($filtros['departamento_id'] ?? null, fn ($q, $v) => $q->where('departamento_id', $v))
            ->get();

        $this->registrarExportacion($usuarioId, 'activos', $filtros);

        return Pdf::loadView('reportes.activos', ['activos' => $activos])->output();
    }

    public function riesgosPdf(int $usuarioId, array $filtros = []): string
    {
        $riesgos = Riesgo::query()
            ->with(['activo', 'amenaza', 'vulnerabilidad'])
            ->when($filtros['nivel_calculado'] ?? null, fn ($q, $v) => $q->where('nivel_calculado', $v))
            ->get();

        $this->registrarExportacion($usuarioId, 'riesgos', $filtros);

        return Pdf::loadView('reportes.riesgos', ['riesgos' => $riesgos])->output();
    }

    public function indicadoresPdf(int $usuarioId, array $indicadores): string
    {
        $this->registrarExportacion($usuarioId, 'indicadores', []);

        return Pdf::loadView('reportes.indicadores', ['indicadores' => $indicadores])->output();
    }

    public function registrarExportacion(int $usuarioId, string $modulo, array $filtros, string $formato = 'pdf'): void
    {
        DB::transaction(function () use ($usuarioId, $modulo, $filtros, $formato) {
            ExportacionLog::query()->create([
                'usuario_id' => $usuarioId,
                'modulo' => $modulo,
                'formato' => $formato,
                'filtros' => $filtros,
                'created_at' => now(),
            ]);
        });
    }
}