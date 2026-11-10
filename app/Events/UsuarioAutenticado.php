<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Usuario;
use Illuminate\Foundation\Events\Dispatchable;

class UsuarioAutenticado
{
    use Dispatchable;

    public function __construct(public readonly Usuario $usuario)
    {
    }
}