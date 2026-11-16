<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Historial de mantenimiento de un equipo (ISO 27001 A.7.13). */
class MantenimientoEquipo extends Model
{
    protected $table = 'mantenimientos_equipo';

    protected $fillable = ['computadora_id', 'tipo', 'fecha', 'descripcion', 'realizado_por', 'estado_resultante', 'registrado_por'];

    protected $casts = ['fecha' => 'date'];

    public function computadora(): BelongsTo
    {
        return $this->belongsTo(Computadora::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por');
    }
}
