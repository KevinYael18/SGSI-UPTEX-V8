<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ConfiguracionSistema;
use App\Repositories\Contracts\ConfiguracionRepositoryInterface;

class ConfiguracionRepository extends BaseRepository implements ConfiguracionRepositoryInterface
{
    public function __construct(ConfiguracionSistema $modelo)
    {
        parent::__construct($modelo);
    }

    public function obtenerPorClave(string $clave): ?string
    {
        return $this->query()->where('clave', $clave)->value('valor');
    }

    public function establecer(string $clave, string $valor): void
    {
        $this->query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }
}