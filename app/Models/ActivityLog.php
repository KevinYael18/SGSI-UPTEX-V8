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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}