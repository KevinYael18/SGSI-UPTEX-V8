<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TieneUuidPublico;
use App\Support\InventarioCatalogos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ficha técnica de una computadora. Cada computadora ES un Activo (1:1):
 * riesgos, incidentes, controles ISO, responsable y departamento se
 * gestionan a través de `activo`, sin duplicar esos módulos.
 */
class Computadora extends Model
{
    use SoftDeletes, TieneUuidPublico;

    protected $table = 'computadoras';

    public const CAMPOS_FICHA = [
        'laboratorio_id', 'nombre_equipo', 'numero_inventario', 'numero_serie', 'direccion_mac', 'direccion_ip',
        'ubicacion_fisica', 'estado_operativo', 'fabricante', 'modelo', 'procesador', 'ram_gb', 'almacenamiento_gb',
        'tipo_almacenamiento', 'tarjeta_grafica', 'fecha_adquisicion', 'garantia_hasta',
        'so_nombre', 'so_edicion', 'so_version', 'so_arquitectura', 'so_activacion', 'so_ultima_actualizacion', 'so_soporte',
        'antivirus_nombre', 'antivirus_fabricante', 'antivirus_version', 'antivirus_instalacion', 'antivirus_proteccion',
        'antivirus_ultima_actualizacion', 'observaciones',
    ];

    protected $fillable = ['activo_id', ...self::CAMPOS_FICHA];

    protected $casts = [
        'fecha_adquisicion' => 'date',
        'garantia_hasta' => 'date',
        'so_ultima_actualizacion' => 'date',
        'antivirus_ultima_actualizacion' => 'date',
        'ram_gb' => 'integer',
        'almacenamiento_gb' => 'integer',
    ];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }

    public function laboratorio(): BelongsTo
    {
        return $this->belongsTo(Laboratorio::class);
    }

    /** Programas INSTALADOS actualmente (los desinstalados quedan en el historial). */
    public function software(): BelongsToMany
    {
        return $this->softwareRegistrado()->wherePivot('estado_instalacion', 'instalado');
    }

    /** Todas las instalaciones registradas del equipo, incluidas las desinstaladas. */
    public function softwareRegistrado(): BelongsToMany
    {
        return $this->belongsToMany(Software::class, 'computadora_software', 'computadora_id', 'software_id')
            ->using(InstalacionSoftware::class)
            ->withPivot(['id', 'estado_instalacion', 'version', 'fecha_instalacion', 'estado_licencia', 'requiere_actualizacion', 'observaciones'])
            ->withTimestamps();
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(MantenimientoEquipo::class)->orderByDesc('fecha')->orderByDesc('id');
    }

    /**
     * Estado del antivirus: "actualizado" | "desactualizado" | "sin_informacion".
     * Actualizado = instalado + protección activa + actualizado dentro del plazo
     * configurado. Si falta información NO se presume que está bien ni mal.
     */
    public function estadoAntivirus(): string
    {
        // Sin antivirus o con la protección apagada: requiere atención.
        if ($this->antivirus_instalacion === 'no_instalado' || $this->antivirus_proteccion === 'inactiva') {
            return 'desactualizado';
        }
        // Datos incompletos: no se presume ni bueno ni malo.
        if ($this->antivirus_instalacion !== 'instalado'
            || $this->antivirus_proteccion !== 'activa'
            || $this->antivirus_ultima_actualizacion === null) {
            return 'sin_informacion';
        }
        $limite = now()->startOfDay()->subDays(InventarioCatalogos::diasAntivirus());

        return $this->antivirus_ultima_actualizacion->lt($limite) ? 'desactualizado' : 'actualizado';
    }

    /** Número de programas instalados que requieren actualización. */
    public function softwarePendienteActualizacion(): int
    {
        return $this->software->filter(fn (Software $s) => InstalacionSoftware::estaDesactualizada(
            (bool) $s->pivot->requiere_actualizacion,
            $s->pivot->version,
            $s->version_vigente,
        ))->count();
    }
}
