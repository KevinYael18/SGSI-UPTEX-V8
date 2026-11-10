<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comentario;
use App\Models\Usuario;

class ComentarioPolicy
{
    /**
     * Solo el autor (o Administrador, ya cubierto por Gate::before) puede
     * borrar su propio comentario — nadie edita comentarios ajenos.
     */
    public function delete(Usuario $actor, Comentario $comentario): bool
    {
        return $comentario->usuario_id === $actor->id;
    }
}