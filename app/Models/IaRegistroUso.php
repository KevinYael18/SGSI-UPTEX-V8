<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Una llamada al proveedor de IA (o un rechazo local): base del control de consumo. */
class IaRegistroUso extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ia_registros_uso';

    protected $fillable = [
        'usuario_id', 'operacion', 'proveedor', 'modelo', 'categoria',
        'tokens_entrada', 'tokens_salida', 'exito', 'codigo_error', 'latencia_ms',
    ];

    protected $casts = ['exito' => 'boolean'];
}
