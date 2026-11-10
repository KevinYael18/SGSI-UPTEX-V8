<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\EstadoControlIso;
use App\Models\Activo;
use App\Models\Amenaza;
use App\Models\Vulnerabilidad;

/**
 * SRP: única responsabilidad es estimar la probabilidad (1-5) de un riesgo.
 *
 * Modelo semicuantitativo, en la línea de ISO/IEC 27005:2022 con escala 1-5:
 *   probabilidad = base(amenaza) + ajuste(vulnerabilidad, controles del activo)
 *
 * - base(amenaza): frecuencia típica según la categoría de la amenaza.
 * - ajuste: el control ISO que mitiga la vulnerabilidad determina el ajuste:
 *     +1 si el control NO está vinculado al activo (exposición),
 *      0 si está vinculado pero NO implementado,
 *     -1 si está vinculado e implementado.
 *
 * Los valores son supuestos del modelo y deben aprobarse en el SGSI.
 */
final class ProbabilidadRiesgoCalculator
{
    /** Frecuencia base por categoría de amenaza (escala 1-5). */
    private const BASE_POR_CATEGORIA_AMENAZA = [
        'humana_intencional' => 3,
        'humana_no_intencional' => 3,
        'tecnica' => 3,
        'ambiental' => 2,
    ];

    private const BASE_POR_DEFECTO = 3;

    /** Control ISO 27001:2022 Anexo A que mitiga cada vulnerabilidad del catálogo. */
    private const CONTROL_POR_VULNERABILIDAD = [
        'Contraseñas débiles o reutilizadas' => 'A.5.17',
        'Software sin parches de seguridad' => 'A.8.8',
        'Falta de cifrado en datos sensibles' => 'A.8.24',
        'Ausencia de respaldo periódico' => 'A.8.13',
        'Control de acceso físico insuficiente' => 'A.7.2',
        'Personal sin capacitación en seguridad' => 'A.6.3',
        'Segmentación de red inexistente' => 'A.8.22',
        'Falta de monitoreo/logging centralizado' => 'A.8.15',
    ];

    public function calcular(Activo $activo, Amenaza $amenaza, Vulnerabilidad $vulnerabilidad): int
    {
        $base = self::BASE_POR_CATEGORIA_AMENAZA[$amenaza->categoria] ?? self::BASE_POR_DEFECTO;

        return max(1, min(5, $base + $this->ajuste($activo, $vulnerabilidad)));
    }

    private function ajuste(Activo $activo, Vulnerabilidad $vulnerabilidad): int
    {
        $codigo = self::CONTROL_POR_VULNERABILIDAD[$vulnerabilidad->nombre] ?? null;

        if ($codigo === null) {
            return 0;
        }

        $control = $activo->controlesIso()->where('codigo', $codigo)->first();

        if ($control === null) {
            return 1;
        }

        return $control->estado_implementacion === EstadoControlIso::Implementado ? -1 : 0;
    }
}
