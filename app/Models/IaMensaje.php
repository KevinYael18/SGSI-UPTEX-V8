<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IaMensaje extends Model
{
    public const UPDATED_AT = null;

    public const ROL_USUARIO = 'usuario';
    public const ROL_ASISTENTE = 'asistente';

    public const ESTADO_OK = 'ok';
    public const ESTADO_RECHAZADO = 'rechazado';
    public const ESTADO_BLOQUEADO = 'bloqueado';
    public const ESTADO_ERROR = 'error';

    protected $table = 'ia_mensajes';

    protected $fillable = [
        'conversacion_id', 'rol', 'contenido', 'categoria', 'estado',
        'fuentes', 'metadatos', 'tokens_entrada', 'tokens_salida',
    ];

    protected $casts = ['fuentes' => 'array', 'metadatos' => 'array'];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(IaConversacion::class, 'conversacion_id');
    }
}
