<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Laboratorio de cómputo dentro de un edificio; agrupa computadoras individuales. */
class Laboratorio extends Model
{
    protected $table = 'laboratorios';

    protected $fillable = ['edificio_id', 'departamento_id', 'ubicacion_id', 'responsable_id', 'nombre', 'clave', 'capacidad', 'descripcion'];

    public function edificio(): BelongsTo
    {
        return $this->belongsTo(Edificio::class);
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

    public function computadoras(): HasMany
    {
        return $this->hasMany(Computadora::class);
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' · ' . ($this->edificio?->nombre ?? ''), ' ·');
    }
}
