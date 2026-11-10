<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    use HasFactory;

    protected $table = 'departamentos';
    protected $fillable = ['clave', 'nombre'];

    public function ubicaciones(): HasMany
    {
        return $this->hasMany(Ubicacion::class);
    }

    public function activos(): HasMany
    {
        return $this->hasMany(Activo::class);
    }

    public function usuariosConAlcance(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'user_department_scope', 'departamento_id', 'usuario_id');
    }
}