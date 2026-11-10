<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Comentario;
use App\Repositories\Contracts\ComentarioRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ComentarioRepository implements ComentarioRepositoryInterface
{
    public function paraEntidad(Model $comentable): Collection
    {
        return $comentable->comentarios()->with('usuario')->get();
    }

    public function crear(Model $comentable, int $usuarioId, string $contenido): Model
    {
        return $comentable->comentarios()->create([
            'usuario_id' => $usuarioId,
            'contenido' => $contenido,
        ]);
    }
}