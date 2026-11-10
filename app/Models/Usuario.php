<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoUsuario;
use App\Models\Concerns\TieneUuidPublico;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasFactory, SoftDeletes, TieneUuidPublico, HasApiTokens, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = ['nombre', 'email', 'password_hash', 'rol_id', 'estado'];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'estado' => EstadoUsuario::class,
        'ultimo_acceso' => 'datetime',
        'bloqueado_hasta' => 'datetime',
    ];

    // Sanctum/Auth esperan getAuthPassword(); el campo se llama password_hash
    // por convención explícita del diccionario de datos (Fase 2 §5.1).
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }

    public function departamentosConAlcance(): BelongsToMany
    {
        return $this->belongsToMany(Departamento::class, 'user_department_scope', 'usuario_id', 'departamento_id');
    }

    public function activosResponsable(): HasMany
    {
        return $this->hasMany(Activo::class, 'responsable_id');
    }

    public function incidentesReportados(): HasMany
    {
        return $this->hasMany(Incidente::class, 'reportado_por');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class);
    }

    public function tienePermiso(string $clave): bool
    {
        return $this->rol->permisos()->where('clave', $clave)->exists();
    }

    public function bloqueado(): bool
    {
        return $this->bloqueado_hasta !== null && $this->bloqueado_hasta->isFuture();
    }
    public function sendPasswordResetNotification($token): void
    {
    $this->notify(new \App\Notifications\RestablecerPasswordNotification($token));
    } 
}