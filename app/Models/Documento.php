<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoDocumento;
use App\Models\Concerns\TieneUuidPublico;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Documento extends Model
{
    use HasFactory, SoftDeletes, TieneUuidPublico;

    protected $table = 'documentos';
    protected $fillable = ['titulo', 'estado', 'version_actual', 'publicado_por', 'publicado_en'];

    protected $casts = ['estado' => EstadoDocumento::class, 'publicado_en' => 'datetime'];

    public function versiones(): HasMany
    {
        return $this->hasMany(DocumentoVersion::class);
    }

    public function publicadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'publicado_por');
    }

    public function versionActual(): ?DocumentoVersion
    {
        return $this->versiones()->where('numero_version', $this->version_actual)->first();
    }
}