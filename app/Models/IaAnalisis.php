<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TieneUuidPublico;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Propuesta generada por SGSI Intelligence Assistant en un módulo. Nunca
 * modifica registros oficiales por sí misma: el responsable la acepta,
 * la rechaza o la aplica parcialmente, y esa decisión queda en bitácora.
 */
class IaAnalisis extends Model
{
    use TieneUuidPublico;

    public const ESTADOS_DECISION = ['aceptada', 'rechazada', 'parcial'];

    protected $table = 'ia_analisis';

    protected $fillable = [
        'usuario_id', 'tipo', 'entidad_tipo', 'entidad_id', 'entrada', 'resultado',
        'estado_propuesta', 'decidido_por', 'decidido_en', 'proveedor', 'modelo',
        'tokens_entrada', 'tokens_salida',
    ];

    protected $casts = ['entrada' => 'array', 'resultado' => 'array', 'decidido_en' => 'datetime'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
