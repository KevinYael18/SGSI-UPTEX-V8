<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Auditoria extends Model
{
    use HasFactory;

    protected $table = 'auditorias';
    protected $fillable = ['alcance', 'fecha_inicio', 'fecha_fin', 'auditor_id'];

    protected $casts = ['fecha_inicio' => 'date', 'fecha_fin' => 'date'];

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'auditor_id');
    }

    public function hallazgos(): HasMany
    {
        return $this->hasMany(Hallazgo::class);
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