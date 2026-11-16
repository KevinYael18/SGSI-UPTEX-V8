<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TipoActivo;
use App\Models\Activo;
use App\Models\Computadora;
use App\Models\ControlIso;
use App\Models\InstalacionSoftware;
use App\Models\Laboratorio;
use App\Models\MantenimientoEquipo;
use App\Models\Software;
use App\Models\Usuario;
use App\Support\Bitacora;
use App\Support\InventarioCatalogos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reglas del inventario tecnológico. Reutiliza ActivoService para crear y
 * actualizar el Activo de cada equipo (criticidad, controles ISO, eventos y
 * bitácora del módulo de Activos), en lugar de duplicar esa lógica.
 */
class InventarioService
{
    private const CAMPOS_ACTIVO = ['confidencialidad', 'integridad', 'disponibilidad', 'departamento_id', 'responsable_id', 'controles_iso_ids'];

    public function __construct(private readonly ActivoService $activos)
    {
    }

    /** Computadoras visibles para el usuario (rol Consulta: solo sus departamentos). */
    public function visiblesPara(Usuario $usuario): Builder
    {
        $consulta = Computadora::query();

        if ($usuario->rol?->nombre === 'Consulta') {
            $departamentos = $usuario->departamentosConAlcance()->pluck('departamentos.id');
            $consulta->whereHas('activo', fn ($q) => $q->whereIn('departamento_id', $departamentos));
        }

        return $consulta;
    }

    /**
     * Crea un laboratorio y su ubicación en el catálogo existente (para que los
     * activos de sus equipos aparezcan ubicados también en el módulo de Activos).
     */
    public function crearLaboratorio(array $datos): Laboratorio
    {
        return DB::transaction(function () use ($datos) {
            $edificio = \App\Models\Edificio::query()->findOrFail($datos['edificio_id']);
            $ubicacion = \App\Models\Ubicacion::query()->create([
                'departamento_id' => $datos['departamento_id'],
                'edificio_id' => $edificio->id,
                'nombre' => $datos['nombre'],
                'edificio' => $edificio->nombre,
                'tipo_espacio' => 'laboratorio',
            ]);

            return Laboratorio::query()->create($datos + ['ubicacion_id' => $ubicacion->id]);
        });
    }

    public function crearComputadora(array $datos, Usuario $actor): Computadora
    {
        return DB::transaction(function () use ($datos, $actor) {
            $laboratorio = isset($datos['laboratorio_id']) ? Laboratorio::query()->find($datos['laboratorio_id']) : null;

            if (!empty($datos['activo_id'])) {
                $activo = $this->activoVinculable((int) $datos['activo_id']);
                if ($laboratorio?->ubicacion_id && $activo->ubicacion_id !== $laboratorio->ubicacion_id) {
                    $activo->forceFill(['ubicacion_id' => $laboratorio->ubicacion_id])->save();
                }
            } else {
                $departamentoId = $datos['departamento_id'] ?? $laboratorio?->departamento_id;
                if ($departamentoId === null) {
                    throw ValidationException::withMessages(['departamento_id' => 'Indica el departamento del equipo o asígnalo a un laboratorio.']);
                }
                $activo = $this->activos->crear([
                    'nombre' => $datos['nombre_equipo'],
                    'tipo' => TipoActivo::Hardware->value,
                    'confidencialidad' => (int) $datos['confidencialidad'],
                    'integridad' => (int) $datos['integridad'],
                    'disponibilidad' => (int) $datos['disponibilidad'],
                    'departamento_id' => (int) $departamentoId,
                    'ubicacion_id' => $laboratorio?->ubicacion_id,
                    'responsable_id' => $datos['responsable_id'] ?? $laboratorio?->responsable_id,
                    'controles_iso_ids' => $datos['controles_iso_ids'] ?? [],
                ], $actor->id);
            }

            $computadora = Computadora::query()->create(
                ['activo_id' => $activo->id] + array_intersect_key($datos, array_flip(Computadora::CAMPOS_FICHA))
            );

            Bitacora::registrar('computadoras', $computadora->id, $actor->id, 'create', [
                'evento' => empty($datos['activo_id']) ? 'computadora_registrada' : 'computadora_vinculada_a_activo_existente',
                'nombre_equipo' => $computadora->nombre_equipo,
                'numero_inventario' => $computadora->numero_inventario,
                'laboratorio' => $laboratorio?->nombreCompleto(),
                'activo_uuid' => $activo->uuid,
            ]);

            return $computadora;
        });
    }

    public function actualizarComputadora(Computadora $computadora, array $datos, Usuario $actor): Computadora
    {
        return DB::transaction(function () use ($computadora, $datos, $actor) {
            $ficha = array_intersect_key($datos, array_flip(Computadora::CAMPOS_FICHA));
            $antes = $computadora->only(array_keys($ficha));
            $laboratorioAnterior = $computadora->laboratorio;

            $computadora->fill($ficha)->save();
            $computadora->refresh();

            // Datos que viven en el Activo (nombre, CIA, responsable, departamento,
            // controles): se delegan a ActivoService para conservar su lógica y eventos.
            $paraActivo = array_intersect_key($datos, array_flip(self::CAMPOS_ACTIVO));
            if (array_key_exists('nombre_equipo', $ficha)) {
                $paraActivo['nombre'] = $ficha['nombre_equipo'];
            }
            if (array_key_exists('laboratorio_id', $ficha) && $computadora->laboratorio_id !== $laboratorioAnterior?->id) {
                $paraActivo['ubicacion_id'] = $computadora->laboratorio?->ubicacion_id;
            }
            $controles = $paraActivo['controles_iso_ids'] ?? null;
            unset($paraActivo['controles_iso_ids']);
            if ($paraActivo !== []) {
                $this->activos->actualizar($computadora->activo, $paraActivo, $actor->id);
            }
            if (is_array($controles)) {
                $computadora->activo->controlesIso()->sync($controles);
            }

            $cambios = Bitacora::diferencias($antes, $computadora->only(array_keys($ficha)));
            if (isset($cambios['laboratorio_id'])) {
                $cambios['laboratorio_id']['antes_nombre'] = $laboratorioAnterior?->nombreCompleto();
                $cambios['laboratorio_id']['despues_nombre'] = $computadora->laboratorio?->nombreCompleto();
            }
            if ($cambios !== [] || is_array($controles)) {
                $evento = isset($cambios['laboratorio_id']) || isset($cambios['ubicacion_fisica']) ? 'cambio_ubicacion_equipo' : 'ficha_tecnica_actualizada';
                Bitacora::registrar('computadoras', $computadora->id, $actor->id, 'update', ['evento' => $evento] + $cambios
                    + (is_array($controles) ? ['controles_iso_ids' => $controles] : []));
            }

            return $computadora->fresh();
        });
    }

    /** Elimina (lógicamente) el equipo y su activo; bloqueado si tiene riesgos abiertos. */
    public function eliminarComputadora(Computadora $computadora, Usuario $actor): void
    {
        DB::transaction(function () use ($computadora, $actor) {
            $this->activos->eliminar($computadora->activo); // lanza excepción si hay riesgos abiertos
            $computadora->delete();
            Bitacora::registrar('computadoras', $computadora->id, $actor->id, 'delete', [
                'evento' => 'computadora_eliminada',
                'nombre_equipo' => $computadora->nombre_equipo,
                'numero_inventario' => $computadora->numero_inventario,
            ]);
        });
    }

    public function instalarSoftware(Computadora $computadora, Software $software, array $datos, Usuario $actor): void
    {
        $previa = $computadora->softwareRegistrado()->whereKey($software->id)->first();
        if ($previa !== null && $previa->pivot->estado_instalacion === 'instalado') {
            throw ValidationException::withMessages(['software_id' => 'Este programa ya está registrado en el equipo; edita la instalación existente.']);
        }
        if ($previa !== null) {
            // Se había desinstalado: se reinstala conservando el mismo registro (historial).
            $computadora->softwareRegistrado()->updateExistingPivot($software->id, ['estado_instalacion' => 'instalado'] + $this->datosInstalacion($datos));
        } else {
            $computadora->softwareRegistrado()->attach($software->id, ['estado_instalacion' => 'instalado'] + $this->datosInstalacion($datos));
        }
        Bitacora::registrar('computadoras', $computadora->id, $actor->id, 'update', [
            'evento' => 'software_registrado',
            'software' => $software->nombre,
            'version' => $datos['version'] ?? null,
            'estado_licencia' => $datos['estado_licencia'] ?? 'pendiente_verificacion',
        ]);
    }

    public function actualizarInstalacion(Computadora $computadora, Software $software, array $datos, Usuario $actor): void
    {
        $actual = $computadora->software()->whereKey($software->id)->first();
        if ($actual === null) {
            throw ValidationException::withMessages(['software_id' => 'El programa no está registrado en este equipo.']);
        }
        $nuevos = $this->datosInstalacion($datos);
        $antes = collect($actual->pivot->getAttributes())->only(array_keys($nuevos))->all();
        $computadora->software()->updateExistingPivot($software->id, $nuevos);
        Bitacora::registrar('computadoras', $computadora->id, $actor->id, 'update', [
            'evento' => 'software_modificado',
            'software' => $software->nombre,
        ] + Bitacora::diferencias($antes, $nuevos));
    }

    public function quitarSoftware(Computadora $computadora, Software $software, Usuario $actor): void
    {
        // Se marca como desinstalado (no se borra): queda el historial de instalaciones.
        $computadora->software()->updateExistingPivot($software->id, ['estado_instalacion' => 'desinstalado']);
        Bitacora::registrar('computadoras', $computadora->id, $actor->id, 'update', [
            'evento' => 'software_desinstalado',
            'software' => $software->nombre,
        ]);
    }

    public function registrarMantenimiento(Computadora $computadora, array $datos, Usuario $actor): MantenimientoEquipo
    {
        return DB::transaction(function () use ($computadora, $datos, $actor) {
            $registro = $computadora->mantenimientos()->create($datos + ['registrado_por' => $actor->id]);
            $estadoAnterior = $computadora->estado_operativo;
            if (!empty($datos['estado_resultante']) && $datos['estado_resultante'] !== $estadoAnterior) {
                $computadora->forceFill(['estado_operativo' => $datos['estado_resultante']])->save();
            }
            Bitacora::registrar('computadoras', $computadora->id, $actor->id, 'update', [
                'evento' => 'mantenimiento_registrado',
                'tipo' => $registro->tipo,
                'fecha' => $registro->fecha->toDateString(),
            ] + (!empty($datos['estado_resultante']) && $datos['estado_resultante'] !== $estadoAnterior
                ? ['estado_operativo' => ['antes' => $estadoAnterior, 'despues' => $datos['estado_resultante']]]
                : []));

            return $registro;
        });
    }

    /**
     * Indicadores de un conjunto de computadoras (laboratorio, edificio o todo
     * el inventario). Se calculan SIEMPRE sobre los registros reales.
     */
    public function resumen(Collection $computadoras): array
    {
        $porEstado = array_fill_keys(array_keys(InventarioCatalogos::ESTADOS_OPERATIVOS), 0);
        $antivirus = ['actualizado' => 0, 'desactualizado' => 0, 'sin_informacion' => 0];
        $sistemas = [];
        $conPendientes = 0;
        $sinSoporte = 0;

        foreach ($computadoras as $c) {
            $porEstado[$c->estado_operativo] = ($porEstado[$c->estado_operativo] ?? 0) + 1;
            $antivirus[$c->estadoAntivirus()]++;
            $so = trim(($c->so_nombre ?? '') . ' ' . ($c->so_version ?? '')) ?: 'Sin registrar';
            $sistemas[$so] = ($sistemas[$so] ?? 0) + 1;
            if ($c->softwarePendienteActualizacion() > 0) {
                $conPendientes++;
            }
            if ($c->so_soporte === 'sin_soporte') {
                $sinSoporte++;
            }
        }
        arsort($sistemas);

        return [
            'total' => $computadoras->count(),
            'por_estado' => $porEstado,
            'antivirus' => $antivirus,
            'con_software_pendiente' => $conPendientes,
            'so_sin_soporte' => $sinSoporte,
            'sistemas_operativos' => $sistemas,
        ];
    }

    /** Controles del Anexo A sugeridos para un equipo (los que existan en el catálogo). */
    public function controlesSugeridos(): Collection
    {
        return ControlIso::query()
            ->whereIn('codigo', array_keys(InventarioCatalogos::CONTROLES_SUGERIDOS_COMPUTADORA))
            ->orderBy('id')
            ->get(['id', 'codigo', 'nombre', 'estado_implementacion']);
    }

    private function activoVinculable(int $activoId): Activo
    {
        $activo = Activo::query()->with('computadora')->find($activoId);
        if ($activo === null || $activo->tipo !== TipoActivo::Hardware) {
            throw ValidationException::withMessages(['activo_id' => 'Solo se puede vincular un activo existente de tipo hardware.']);
        }
        if ($activo->computadora !== null) {
            throw ValidationException::withMessages(['activo_id' => 'Ese activo ya tiene una ficha de computadora.']);
        }

        return $activo;
    }

    private function datosInstalacion(array $datos): array
    {
        return [
            'version' => $datos['version'] ?? null,
            'fecha_instalacion' => $datos['fecha_instalacion'] ?? null,
            'estado_licencia' => $datos['estado_licencia'] ?? 'pendiente_verificacion',
            'requiere_actualizacion' => (bool) ($datos['requiere_actualizacion'] ?? false),
            'observaciones' => $datos['observaciones'] ?? null,
        ];
    }

    /** Usado por el resumen: la instalación está desactualizada según catálogo o marca manual. */
    public static function instalacionDesactualizada(Software $software): bool
    {
        return InstalacionSoftware::estaDesactualizada(
            (bool) $software->pivot->requiere_actualizacion,
            $software->pivot->version,
            $software->version_vigente,
        );
    }
}
