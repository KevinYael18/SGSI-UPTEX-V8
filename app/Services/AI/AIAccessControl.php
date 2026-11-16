<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Exceptions\AutorizacionInsuficienteException;
use App\Models\Activo;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;

/**
 * Autorización del asistente. La IA nunca decide qué puede ver un usuario:
 * cada consulta de contexto y cada análisis pasa por aquí, con los MISMOS
 * permisos del módulo correspondiente (RolesPermisosSeeder / Policies).
 * Una pregunta conversacional no puede ampliar el alcance de un rol.
 */
final class AIAccessControl
{
    /** Módulo de GSI UPTex → permiso de lectura que exige. */
    public const PERMISO_MODULO = [
        'activos' => 'activos.ver',
        'riesgos' => 'riesgos.ver',
        'incidentes' => 'incidentes.ver',
        'auditorias' => 'auditorias.ver',
        'documentos' => 'documentos.ver',
        'cumplimiento' => 'cumplimiento.ver',
        'dashboard' => 'dashboard.ver',
    ];

    /** @var array<string, bool> caché por petición */
    private array $cache = [];

    public function puede(Usuario $usuario, string $permiso): bool
    {
        $clave = $usuario->id . '|' . $permiso;

        return $this->cache[$clave] ??= $usuario->rol?->nombre === 'Administrador' || $usuario->tienePermiso($permiso);
    }

    public function exigir(Usuario $usuario, string $permiso, string $accion): void
    {
        if (!$this->puede($usuario, $permiso)) {
            throw new AutorizacionInsuficienteException($accion);
        }
    }

    public function puedeModulo(Usuario $usuario, string $modulo): bool
    {
        $permiso = self::PERMISO_MODULO[$modulo] ?? null;

        return $permiso !== null && $this->puede($usuario, $permiso);
    }

    /** @return list<string> módulos que el usuario puede consultar a través del asistente */
    public function modulosPermitidos(Usuario $usuario): array
    {
        return array_values(array_filter(array_keys(self::PERMISO_MODULO), fn ($m) => $this->puedeModulo($usuario, $m)));
    }

    /**
     * Restringe una consulta de activos al alcance del usuario: el rol Consulta
     * solo ve activos de sus departamentos (misma regla que ActivoPolicy::view).
     */
    public function acotarActivos(Usuario $usuario, Builder $consulta, string $columnaDepartamento = 'departamento_id'): Builder
    {
        if ($usuario->rol?->nombre === 'Consulta') {
            $consulta->whereIn($columnaDepartamento, $usuario->departamentosConAlcance()->pluck('departamentos.id'));
        }

        return $consulta;
    }

    public function puedeVerActivo(Usuario $usuario, Activo $activo): bool
    {
        return $usuario->can('view', $activo);
    }
}
