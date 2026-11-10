<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CategoriaControlIso;
use App\Enums\EstadoControlIso;
use App\Repositories\Contracts\ControlIsoRepositoryInterface;

/**
 * SRP puro: solo calcula porcentajes de madurez. Sin efectos secundarios,
 * 100% testeable con Unit tests (no toca BD directamente en los cálculos,
 * recibe colecciones ya cargadas por el Repository).
 */
final class ComplianceCalculator
{
    public function __construct(private readonly ControlIsoRepositoryInterface $controles)
    {
    }

    public function porcentajeGeneral(): float
    {
        $conteo = $this->controles->conteoPorEstado();
        $total = array_sum($conteo);

        if ($total === 0) {
            return 0.0;
        }

        $aplicables = $total - ($conteo[EstadoControlIso::NoAplica->value] ?? 0);
        $implementados = $conteo[EstadoControlIso::Implementado->value] ?? 0;

        return $aplicables > 0 ? round(($implementados / $aplicables) * 100, 1) : 0.0;
    }

    public function porcentajePorCategoria(): array
    {
        $resultado = [];

        foreach (CategoriaControlIso::cases() as $categoria) {
            $controles = $this->controles->porCategoria($categoria);
            $aplicables = $controles->where('estado_implementacion', '!=', EstadoControlIso::NoAplica)->count();
            $implementados = $controles->where('estado_implementacion', EstadoControlIso::Implementado)->count();

            $resultado[$categoria->value] = $aplicables > 0 ? round(($implementados / $aplicables) * 100, 1) : 0.0;
        }

        return $resultado;
    }
}