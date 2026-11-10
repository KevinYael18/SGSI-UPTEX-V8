<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClausulaIso extends Model
{
    protected $table = 'clausulas_iso';
    protected $fillable = ['numero', 'nombre'];

    public function controles(): HasMany
    {
        return $this->hasMany(ControlIso::class, 'clausula_id');
    }
}