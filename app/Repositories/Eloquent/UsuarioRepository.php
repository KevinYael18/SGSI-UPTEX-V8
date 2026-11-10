<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Usuario;
use App\Repositories\Contracts\UsuarioRepositoryInterface;

class UsuarioRepository extends BaseRepository implements UsuarioRepositoryInterface
{
    public function __construct(Usuario $modelo)
    {
        parent::__construct($modelo);
    }

    public function buscarPorEmail(string $email): ?Usuario
    {
        return $this->query()->where('email', $email)->first();
    }

    public function registrarIntentoFallido(Usuario $usuario): void
    {
        $usuario->increment('intentos_fallidos');

        if ($usuario->intentos_fallidos >= 5) {
            $usuario->forceFill(['bloqueado_hasta' => now()->addMinutes(15)])->save();
        }
    }

    public function reiniciarIntentos(Usuario $usuario): void
    {
        $usuario->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => null, 'ultimo_acceso' => now()])->save();
    }
}