<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NivelRiesgo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatrizRiesgo extends Model
{
    use HasFactory;

    protected $table = 'matriz_riesgo';
    protected $fillable = ['probabilidad', 'impacto', 'nivel_resultante'];

    protected $casts = [
        'nivel_resultante' => NivelRiesgo::class,
    ];
}