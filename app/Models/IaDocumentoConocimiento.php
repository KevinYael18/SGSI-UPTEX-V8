<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TieneUuidPublico;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Documento autorizado de la base de conocimiento (RAG) del asistente. */
class IaDocumentoConocimiento extends Model
{
    use SoftDeletes, TieneUuidPublico;

    public const CATEGORIAS = ['institucional', 'procedimiento', 'politica', 'tecnica', 'normativa'];

    protected $table = 'ia_documentos_conocimiento';

    protected $fillable = [
        'titulo', 'categoria', 'descripcion', 'nombre_archivo', 'mime_type', 'hash_sha256',
        'estado', 'error', 'total_fragmentos', 'fragmentos_sospechosos', 'activo', 'subido_por',
    ];

    protected $casts = ['activo' => 'boolean'];

    public function fragmentos(): HasMany
    {
        return $this->hasMany(IaFragmentoDocumento::class, 'documento_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'subido_por');
    }
}
