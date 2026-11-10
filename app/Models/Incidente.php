<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoIncidente;
use App\Models\Concerns\TieneUuidPublico;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incidente extends Model
{
    use HasFactory, SoftDeletes, TieneUuidPublico;

    protected $table = 'incidentes';

    protected $fillable = ['activo_id', 'reportado_por', 'titulo', 'descripcion', 'estado', 'fecha_deteccion'];

    protected $casts = [
        'estado' => EstadoIncidente::class,
        'fecha_deteccion' => 'datetime',
    ];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }

    public function reportadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'reportado_por');
    }

    public function evidencias(): MorphMany
    {
        return $this->morphMany(Evidencia::class, 'evidenciable');
    }

    public function comentarios(): MorphMany
    {
        return $this->morphMany(Comentario::class, 'comentable')->latest();
    }
}
