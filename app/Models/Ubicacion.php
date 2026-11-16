<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ubicacion extends Model
{
    protected $table = 'ubicaciones';
    protected $fillable = ['departamento_id', 'edificio_id', 'nombre', 'edificio', 'piso', 'tipo_espacio'];

    public const TIPOS_ESPACIO = [
        'laboratorio' => 'Laboratorio', 'aula' => 'Aula', 'oficina' => 'Oficina', 'area' => 'Área',
        'site' => 'Site / centro de datos', 'almacen' => 'Almacén', 'otro' => 'Otro espacio',
    ];

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function activos(): HasMany
    {
        return $this->hasMany(Activo::class);
    }

    public function edificioRelacion(): BelongsTo
    {
        return $this->belongsTo(Edificio::class, 'edificio_id');
    }

    /** «Laboratorio 1 · Edificio B · Planta baja» (solo con los datos registrados). */
    public function descripcion(): string
    {
        $edificio = $this->edificioRelacion?->nombre ?? $this->edificio;

        return implode(' · ', array_filter([$this->nombre, $edificio, $this->piso]));
    }
}
