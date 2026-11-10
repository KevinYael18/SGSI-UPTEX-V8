<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TieneUuidPublico;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Evidencia extends Model
{
    use TieneUuidPublico;

    protected $table = 'evidencias';

    protected $fillable = [
        'evidenciable_type', 'evidenciable_id', 'subido_por', 'path_storage',
        'mime_type', 'hash_sha256', 'fecha_expiracion',
    ];

    protected $casts = [
        'fecha_expiracion' => 'date',
    ];

    public function evidenciable(): MorphTo
    {
        return $this->morphTo();
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'subido_por');
    }
}