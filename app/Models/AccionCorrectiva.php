<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoAuditoriaGenerico;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AccionCorrectiva extends Model
{
    protected $table = 'acciones_correctivas';
    protected $fillable = ['no_conformidad_id', 'responsable_id', 'fecha_compromiso', 'estado'];

    protected $casts = [
        'estado' => EstadoAuditoriaGenerico::class,
        'fecha_compromiso' => 'date',
    ];

    public function noConformidad(): BelongsTo
    {
        return $this->belongsTo(NoConformidad::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    public function comentarios(): MorphMany
    {
        return $this->morphMany(Comentario::class, 'comentable')->latest();
    }
}