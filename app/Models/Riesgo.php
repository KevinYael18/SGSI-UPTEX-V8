<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NivelRiesgo;
use App\Enums\TipoTratamientoRiesgo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Riesgo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'riesgos';

    protected $fillable = [
        'activo_id', 'amenaza_id', 'vulnerabilidad_id', 'probabilidad', 'impacto',
        'nivel_calculado', 'tipo_tratamiento',
        'probabilidad_residual', 'impacto_residual', 'nivel_residual', 'fecha_revision',
    ];

    protected $casts = [
        'fecha_revision' => 'date',
        'nivel_calculado' => NivelRiesgo::class,
        'tipo_tratamiento' => TipoTratamientoRiesgo::class,
    ];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }

    public function amenaza(): BelongsTo
    {
        return $this->belongsTo(Amenaza::class);
    }

    public function vulnerabilidad(): BelongsTo
    {
        return $this->belongsTo(Vulnerabilidad::class);
    }

    public function planesTratamiento(): HasMany
    {
        return $this->hasMany(PlanTratamiento::class);
    }

    public function controlesIso(): BelongsToMany
    {
        return $this->belongsToMany(ControlIso::class, 'riesgo_control', 'riesgo_id', 'control_id');
    }

    public function comentarios(): MorphMany
    {
        return $this->morphMany(Comentario::class, 'comentable')->latest();
    }
}