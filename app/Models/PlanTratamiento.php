<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoAuditoriaGenerico;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanTratamiento extends Model
{
    protected $table = 'planes_tratamiento';
    protected $fillable = ['riesgo_id', 'descripcion', 'responsable_id', 'fecha_objetivo', 'estado'];

    protected $casts = [
        'estado' => EstadoAuditoriaGenerico::class,
        'fecha_objetivo' => 'date',
    ];

    public function riesgo(): BelongsTo
    {
        return $this->belongsTo(Riesgo::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }
}