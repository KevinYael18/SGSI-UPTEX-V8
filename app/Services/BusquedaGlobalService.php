<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activo;
use App\Models\Auditoria;
use App\Models\ControlIso;
use App\Models\Documento;
use App\Models\Incidente;
use App\Models\Riesgo;
use Illuminate\Support\Collection;

/**
 * Búsqueda funcional simple (LIKE) sobre los módulos principales. Se deja
 * documentado como punto de extensión: si el volumen de datos crece,
 * reemplazar por Laravel Scout + un motor (Meilisearch/typesense) es un
 * cambio contenido a esta clase — el resto del sistema no la conoce
 * directamente, solo el Controller.
 */
class BusquedaGlobalService
{
    private const LIMITE_POR_MODULO = 5;

    public function buscar(string $termino): Collection
    {
        $like = "%{$termino}%";

        $activos = Activo::query()->where('nombre', 'like', $like)->limit(self::LIMITE_POR_MODULO)
            ->get()->map(fn ($a) => $this->resultado('activo', $a->uuid, $a->nombre));

        $riesgos = Riesgo::query()->whereHas('activo', fn ($q) => $q->where('nombre', 'like', $like))
            ->limit(self::LIMITE_POR_MODULO)->get()
            ->map(fn ($r) => $this->resultado('riesgo', (string) $r->id, "Riesgo sobre {$r->activo->nombre}"));

        $incidentes = Incidente::query()->where('titulo', 'like', $like)->limit(self::LIMITE_POR_MODULO)
            ->get()->map(fn ($i) => $this->resultado('incidente', $i->uuid, $i->titulo));

        $auditorias = Auditoria::query()->where('alcance', 'like', $like)->limit(self::LIMITE_POR_MODULO)
            ->get()->map(fn ($a) => $this->resultado('auditoria', (string) $a->id, $a->alcance));

        $documentos = Documento::query()->where('titulo', 'like', $like)->limit(self::LIMITE_POR_MODULO)
            ->get()->map(fn ($d) => $this->resultado('documento', $d->uuid, $d->titulo));

        $controles = ControlIso::query()->where('nombre', 'like', $like)->orWhere('codigo', 'like', $like)
            ->limit(self::LIMITE_POR_MODULO)->get()
            ->map(fn ($c) => $this->resultado('control_iso', (string) $c->id, "{$c->codigo} — {$c->nombre}"));

        return $activos->concat($riesgos)->concat($incidentes)->concat($auditorias)->concat($documentos)->concat($controles);
    }

    private function resultado(string $modulo, string $id, string $titulo): array
    {
        return ['modulo' => $modulo, 'id' => $id, 'titulo' => $titulo];
    }
}