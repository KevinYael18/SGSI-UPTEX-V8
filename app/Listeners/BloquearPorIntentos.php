<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\IntentoFallidoLogin;
use App\Repositories\Contracts\UsuarioRepositoryInterface;

class BloquearPorIntentos
{
    public function __construct(private readonly UsuarioRepositoryInterface $usuarios)
    {
    }

    public function handle(IntentoFallidoLogin $event): void
    {
        $usuario = $this->usuarios->buscarPorEmail($event->email);

        if ($usuario !== null) {
            $this->usuarios->registrarIntentoFallido($usuario);
        }
    }
}