<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $table = 'activity_log';
    public $timestamps = false;
    protected $fillable = ['entidad', 'entidad_id', 'usuario_id', 'accion', 'cambios', 'created_at'];

    protected $casts = ['cambios' => 'array', 'created_at' => 'datetime'];

    /**
     * La bitácora es de solo inserción (trazabilidad ISO 27001 8.15): ningún
     * registro puede modificarse ni eliminarse desde la aplicación.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('Los registros de la bitácora no pueden modificarse.');
        });
        static::deleting(function () {
            throw new \LogicException('Los registros de la bitácora no pueden eliminarse.');
        });
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}