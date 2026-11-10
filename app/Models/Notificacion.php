<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    use HasFactory;

    protected $table = 'notificaciones';
    protected $fillable = ['usuario_id', 'tipo', 'datos', 'leida'];

    protected $casts = ['datos' => 'array', 'leida' => 'boolean'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}