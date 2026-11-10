<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoriaControlIso;
use App\Enums\EstadoControlIso;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ControlIso extends Model
{
    use HasFactory;
    protected $table = 'controles_iso';
    protected $fillable = ['codigo', 'categoria', 'clausula_id', 'nombre', 'estado_implementacion'];

    protected $casts = [
        'categoria' => CategoriaControlIso::class,
        'estado_implementacion' => EstadoControlIso::class,
    ];

    public function clausula(): BelongsTo
    {
        return $this->belongsTo(ClausulaIso::class, 'clausula_id');
    }

    public function activos(): BelongsToMany
    {
        return $this->belongsToMany(Activo::class, 'activo_control', 'control_id', 'activo_id');
    }

    public function riesgos(): BelongsToMany
    {
        return $this->belongsToMany(Riesgo::class, 'riesgo_control', 'control_id', 'riesgo_id');
    }
}