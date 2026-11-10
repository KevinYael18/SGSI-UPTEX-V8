<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface RiesgoRepositoryInterface extends RepositoryInterface
{
    public function criticosSinTratar(): int;
}