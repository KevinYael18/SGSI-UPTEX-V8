<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoAuditoriaGenerico;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NoConformidad extends Model
{
    protected $table = 'no_conformidades';
    protected $fillable = ['hallazgo_id', 'estado'];

    protected $casts = ['estado' => EstadoAuditoriaGenerico::class];

    public function hallazgo(): BelongsTo
    {
        return $this->belongsTo(Hallazgo::class);
    }

    public function accionesCorrectivas(): HasMany
    {
        return $this->hasMany(AccionCorrectiva::class);
    }
}