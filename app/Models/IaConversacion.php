<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TieneUuidPublico;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Conversación privada de un usuario con SGSI Intelligence Assistant. */
class IaConversacion extends Model
{
    use SoftDeletes, TieneUuidPublico;

    protected $table = 'ia_conversaciones';

    protected $fillable = ['usuario_id', 'titulo'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(IaMensaje::class, 'conversacion_id');
    }
}
