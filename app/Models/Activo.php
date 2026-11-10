<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoActivo;
use App\Models\Concerns\TieneUuidPublico;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    ];

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

    public function riesgosAbiertosCount(): int
    {
        return $this->riesgos()->whereNull('deleted_at')->count();
    }
}