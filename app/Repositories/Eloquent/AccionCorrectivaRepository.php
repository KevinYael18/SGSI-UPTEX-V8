<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\AccionCorrectiva;
use App\Repositories\Contracts\AccionCorrectivaRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AccionCorrectivaRepository extends BaseRepository implements AccionCorrectivaRepositoryInterface
{
    public function __construct(AccionCorrectiva $modelo)
    {
        parent::__construct($modelo);
    }

    public function vencidasNoMarcadas(): Collection
    {
        return $this->query()
            ->where('fecha_compromiso', '<', now()->toDateString())
            ->whereNotIn('estado', ['cerrada', 'vencida'])
            ->get();
    }
}