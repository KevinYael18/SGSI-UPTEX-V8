<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoVersion extends Model
{
    protected $table = 'documento_version';
    protected $fillable = ['documento_id', 'creado_por', 'numero_version', 'path_storage', 'aprobado_por', 'fecha_expiracion'];

    protected $casts = ['fecha_expiracion' => 'date'];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aprobado_por');
    }
}