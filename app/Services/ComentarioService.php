<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\ComentarioRepositoryInterface;
use App\Support\RegistroComentables;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ComentarioService
{
    public function __construct(private readonly ComentarioRepositoryInterface $comentarios)
    {
    }

    public function resolverEntidadOFallar(string $tipo, string $identificador): Model
    {
        $modelo = RegistroComentables::resolverModelo($tipo, $identificador);

        if ($modelo === null) {
            throw new NotFoundHttpException("No existe {$tipo}/{$identificador}.");
        }

        return $modelo;
    }

    public function listar(Model $comentable): Collection
    {
        return $this->comentarios->paraEntidad($comentable);
    }

    public function crear(Model $comentable, int $usuarioId, string $contenido): Model
    {
        return $this->comentarios->crear($comentable, $usuarioId, $contenido);
    }
}