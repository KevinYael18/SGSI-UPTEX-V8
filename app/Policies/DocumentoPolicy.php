<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Documento;
use App\Models\Usuario;

class DocumentoPolicy
{
    public function viewAny(Usuario $actor): bool
    {
        return $actor->tienePermiso('documentos.ver');
    }

    public function view(Usuario $actor, Documento $documento): bool
    {
        return $actor->tienePermiso('documentos.ver');
    }

    public function create(Usuario $actor): bool
    {
        return $actor->tienePermiso('documentos.crear');
    }

    public function update(Usuario $actor, Documento $documento): bool
    {
        return $actor->tienePermiso('documentos.editar');
    }

    public function delete(Usuario $actor, Documento $documento): bool
    {
        return $actor->tienePermiso('documentos.eliminar');
    }
}