<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activo;
use App\Models\Auditoria;
use App\Models\ControlIso;
use App\Models\Documento;
use App\Models\Incidente;
use App\Models\Riesgo;
use App\Models\Usuario;
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

    public function buscar(string $termino, Usuario $usuario): Collection
    {
        // Se escapan los comodines de LIKE (y la barra, que es el carácter de
        // escape) para que "%" o "_" escritos por el usuario se busquen literalmente.
        $escapado = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termino);
        $like = "%{$escapado}%";

        // Cada módulo solo se consulta si el usuario puede verlo (misma policy
        // `viewAny` que protege el listado del módulo).
        $activos = $this->siPuedeVer($usuario, Activo::class, fn () => Activo::query()->visiblePara($usuario)
            ->where('nombre', 'like', $like)->limit(self::LIMITE_POR_MODULO)->get()
            ->map(fn ($a) => $this->resultado('activo', $a->uuid, $a->nombre)));

        $riesgos = $this->siPuedeVer($usuario, Riesgo::class, fn () => Riesgo::query()
            ->with('activo')->whereHas('activo', fn ($q) => $q->where('nombre', 'like', $like))
            ->limit(self::LIMITE_POR_MODULO)->get()
            ->map(fn ($r) => $this->resultado('riesgo', (string) $r->id, "Riesgo sobre {$r->activo->nombre}")));

        $incidentes = $this->siPuedeVer($usuario, Incidente::class, fn () => Incidente::query()
            ->where('titulo', 'like', $like)->limit(self::LIMITE_POR_MODULO)->get()
            ->map(fn ($i) => $this->resultado('incidente', $i->uuid, $i->titulo)));

        $auditorias = $this->siPuedeVer($usuario, Auditoria::class, fn () => Auditoria::query()
            ->where('alcance', 'like', $like)->limit(self::LIMITE_POR_MODULO)->get()
            ->map(fn ($a) => $this->resultado('auditoria', (string) $a->id, $a->alcance)));

        $documentos = $this->siPuedeVer($usuario, Documento::class, fn () => Documento::query()
            ->where('titulo', 'like', $like)->limit(self::LIMITE_POR_MODULO)->get()
            ->map(fn ($d) => $this->resultado('documento', $d->uuid, $d->titulo)));

        $controles = $this->siPuedeVer($usuario, ControlIso::class, fn () => ControlIso::query()
            ->where('nombre', 'like', $like)->orWhere('codigo', 'like', $like)
            ->limit(self::LIMITE_POR_MODULO)->get()
            ->map(fn ($c) => $this->resultado('control_iso', (string) $c->id, "{$c->codigo} — {$c->nombre}")));

        return $activos->concat($riesgos)->concat($incidentes)->concat($auditorias)->concat($documentos)->concat($controles);
    }

    /** Ejecuta la consulta del módulo solo si el usuario tiene `viewAny` sobre él. */
    private function siPuedeVer(Usuario $usuario, string $modelo, callable $consulta): Collection
    {
        return $usuario->can('viewAny', $modelo) ? $consulta() : collect();
    }

    private function resultado(string $modulo, string $id, string $titulo): array
    {
        return ['modulo' => $modulo, 'id' => $id, 'titulo' => $titulo];
    }
}