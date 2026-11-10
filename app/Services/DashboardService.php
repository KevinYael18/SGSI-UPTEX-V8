<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activo;
use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\Incidente;
use App\Models\MatrizRiesgo;
use App\Models\Riesgo;
use App\Models\Usuario;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\ControlIsoRepositoryInterface;
use App\Repositories\Contracts\DocumentoRepositoryInterface;
use App\Support\CacheVersionado;
use App\Support\ComplianceCalculator;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function __construct(
        private readonly ComplianceCalculator $compliance,
        private readonly DocumentoRepositoryInterface $documentos,
        private readonly ControlIsoRepositoryInterface $controles,
        private readonly AuditoriaRepositoryInterface $auditorias,
    ) {
    }

    public function kpisGenerales(): array
    {
        return Cache::remember(CacheVersionado::clave('dashboard-kpis', 'generales'), now()->addMinutes(10), function () {
            return [
                'cumplimiento_iso' => $this->compliance->porcentajeGeneral(),
                'cumplimiento_por_categoria' => $this->compliance->porcentajePorCategoria(),
                'riesgos_criticos' => Riesgo::query()->where('nivel_calculado', 'critico')->count(),
                'riesgos_sin_tratamiento' => Riesgo::query()->whereNull('tipo_tratamiento')->count(),
                'incidentes_abiertos' => Incidente::query()->whereNotIn('estado', ['cerrado'])->count(),
                'auditorias_en_curso' => Auditoria::query()->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now())->count(),
                'documentos_proximos_a_vencer' => $this->documentos->proximosAExpirar(30)->count(),
                'activos_totales' => Activo::query()->count(),
            ];
        });
    }

    public function paraUsuario(Usuario $usuario): array
    {
        $base = $this->kpisGenerales();

        return match ($usuario->rol->nombre) {
            'Administrador' => $base + ['vista' => 'administrador_completa'],
            'Director' => [
                'cumplimiento_iso' => $base['cumplimiento_iso'],
                'riesgos_criticos' => $base['riesgos_criticos'],
                'incidentes_abiertos' => $base['incidentes_abiertos'],
                'vista' => 'director_ejecutiva',
            ],
            'Auditor' => [
                'auditorias_en_curso' => $base['auditorias_en_curso'],
                'documentos_proximos_a_vencer' => $base['documentos_proximos_a_vencer'],
                'vista' => 'auditor_seguimiento',
            ],
            'Responsable SGSI' => [
                'cumplimiento_iso' => $base['cumplimiento_iso'],
                'cumplimiento_por_categoria' => $base['cumplimiento_por_categoria'],
                'riesgos_criticos' => $base['riesgos_criticos'],
                'riesgos_sin_tratamiento' => $base['riesgos_sin_tratamiento'],
                'vista' => 'sgsi_operativa',
            ],
            'Responsable de Activo' => [
                'activos_totales' => $base['activos_totales'],
                'incidentes_abiertos' => $base['incidentes_abiertos'],
                'vista' => 'responsable_activo',
            ],
            default => [
                'cumplimiento_iso' => $base['cumplimiento_iso'],
                'vista' => 'consulta_limitada',
            ],
        };
    }

    public function matrizRiesgoVisual(): array
    {
        $conteos = Riesgo::query()
            ->selectRaw('probabilidad, impacto, count(*) as total')
            ->groupBy('probabilidad', 'impacto')
            ->get()
            ->keyBy(fn ($fila) => "{$fila->probabilidad}-{$fila->impacto}");

        $niveles = MatrizRiesgo::query()->get()->keyBy(fn ($fila) => "{$fila->probabilidad}-{$fila->impacto}");

        $celdas = [];
        for ($p = 1; $p <= 5; $p++) {
            for ($i = 1; $i <= 5; $i++) {
                $clave = "{$p}-{$i}";
                $celdas[] = [
                    'probabilidad' => $p,
                    'impacto' => $i,
                    'nivel' => $niveles[$clave]->nivel_resultante->value ?? null,
                    'total_riesgos' => $conteos[$clave]->total ?? 0,
                ];
            }
        }

        return $celdas;
    }

    public function incidentesPorEstado(): array
    {
        return Incidente::query()
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();
    }

    public function ultimosIncidentes(int $limite = 5): array
    {
        return Incidente::query()
            ->with('activo')
            ->latest('fecha_deteccion')
            ->limit($limite)
            ->get(['uuid', 'titulo', 'estado', 'activo_id', 'fecha_deteccion'])
            ->map(fn ($i) => [
                'uuid' => $i->uuid,
                'titulo' => $i->titulo,
                'estado' => $i->estado->value,
                'activo' => $i->activo?->nombre,
                'fecha_deteccion' => $i->fecha_deteccion->toIso8601String(),
            ])->all();
    }

    public function documentosRecientes(int $limite = 5): array
    {
        return Documento::query()
            ->latest('created_at')
            ->limit($limite)
            ->get(['uuid', 'titulo', 'estado', 'created_at'])
            ->map(fn ($d) => [
                'uuid' => $d->uuid,
                'titulo' => $d->titulo,
                'estado' => $d->estado->value,
                'creado_en' => $d->created_at->toIso8601String(),
            ])->all();
    }

    public function proximasAuditorias(int $limite = 5): array
    {
        return Auditoria::query()
            ->with('auditor')
            ->whereNotNull('fecha_fin')
            ->where('fecha_fin', '>=', now())
            ->orderBy('fecha_fin')
            ->limit($limite)
            ->get(['id', 'alcance', 'auditor_id', 'fecha_fin'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'alcance' => $a->alcance,
                'auditor' => $a->auditor?->nombre,
                'fecha_fin' => $a->fecha_fin->toDateString(),
            ])->all();
    }

    public function riesgosPorNivel(): array
    {
        return Riesgo::query()
            ->whereNotNull('nivel_calculado')
            ->selectRaw('nivel_calculado, count(*) as total')
            ->groupBy('nivel_calculado')
            ->pluck('total', 'nivel_calculado')
            ->toArray();
    }

    public function auditoriasPorEstado(): array
    {
        $enCurso = Auditoria::query()
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now()))
            ->count();
        $finalizadas = Auditoria::query()->whereNotNull('fecha_fin')->where('fecha_fin', '<', now())->count();

        return ['en_curso' => $enCurso, 'finalizada' => $finalizadas];
    }
}