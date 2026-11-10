<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permiso extends Model
{
    protected $table = 'permisos';
    protected $fillable = ['clave', 'modulo'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'role_permission', 'permiso_id', 'rol_id');
    }
}