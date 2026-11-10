<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Usuario;

interface UsuarioRepositoryInterface extends RepositoryInterface
{
    public function buscarPorEmail(string $email): ?Usuario;

    public function registrarIntentoFallido(Usuario $usuario): void;

    public function reiniciarIntentos(Usuario $usuario): void;
}