<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SeveridadHallazgo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Hallazgo extends Model
{
    protected $table = 'hallazgos';
    protected $fillable = ['auditoria_id', 'severidad', 'descripcion'];

    protected $casts = ['severidad' => SeveridadHallazgo::class];

    public function auditoria(): BelongsTo
    {
        return $this->belongsTo(Auditoria::class);
    }

    public function noConformidades(): HasMany
    {
        return $this->hasMany(NoConformidad::class);
    }

    public function evidencias(): MorphMany
    {
        return $this->morphMany(Evidencia::class, 'evidenciable');
    }
}