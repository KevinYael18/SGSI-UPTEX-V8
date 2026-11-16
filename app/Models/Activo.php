<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoActivo;
use App\Models\Concerns\TieneUuidPublico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activo extends Model
{
    use HasFactory, SoftDeletes, TieneUuidPublico;

    protected $table = 'activos';

    protected $fillable = [
        'nombre', 'tipo', 'confidencialidad', 'integridad', 'disponibilidad',
        'criticidad_calculada', 'departamento_id', 'ubicacion_id', 'responsable_id', 'codigo_qr',
    ];

    protected $casts = [
        'tipo' => TipoActivo::class,
        // Ficha técnica dinámica: {campos: {...}, personalizados: [{nombre, valor}]}
        'caracteristicas' => 'array',
    ];

    /**
     * Activos visibles para un usuario. El rol Consulta solo ve los activos de
     * los departamentos de su alcance — MISMA regla que ActivoPolicy::view,
     * aplicada también a listados, búsquedas, catálogos y reportes (antes solo
     * se aplicaba al detalle: el listado mostraba todos).
     */
    public function scopeVisiblePara(Builder $query, ?Usuario $usuario): Builder
    {
        if ($usuario !== null && $usuario->rol?->nombre === 'Consulta') {
            $query->whereIn($query->qualifyColumn('departamento_id'), $usuario->departamentosConAlcance()->pluck('departamentos.id'));
        }

        return $query;
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    public function riesgos(): HasMany
    {
        return $this->hasMany(Riesgo::class);
    }

    public function incidentes(): HasMany
    {
        return $this->hasMany(Incidente::class);
    }

    public function controlesIso(): BelongsToMany
    {
        return $this->belongsToMany(ControlIso::class, 'activo_control', 'activo_id', 'control_id');
    }

    /** Ficha técnica si el activo es una computadora del inventario tecnológico. */
    public function computadora(): HasOne
    {
        return $this->hasOne(Computadora::class);
    }

    /** Entrada del catálogo de software vinculada (solo activos de tipo software). */
    public function programaCatalogo(): HasOne
    {
        return $this->hasOne(Software::class, 'activo_id');
    }

    public function riesgosAbiertosCount(): int
    {
        return $this->riesgos()->whereNull('deleted_at')->count();
    }
}