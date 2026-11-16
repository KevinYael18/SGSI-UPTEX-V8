<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Edificio de la universidad (nombre configurable por el administrador). */
class Edificio extends Model
{
    protected $table = 'edificios';

    protected $fillable = ['nombre', 'clave', 'descripcion'];

    public function laboratorios(): HasMany
    {
        return $this->hasMany(Laboratorio::class);
    }
}
