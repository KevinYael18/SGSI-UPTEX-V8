<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario;
use App\Services\AI\Analyzers\AIRiskAnalyzer;

/**
 * Punto de entrada original del «Asistente de IA» del módulo de riesgos.
 *
 * Se conserva para no romper integraciones existentes, pero ya no habla con
 * Gemini por su cuenta: delega en el analizador de riesgos de SGSI
 * Intelligence Assistant, que usa la conexión central (App\Services\AI\GeminiService).
 * Su lógica de validación (controles contra el catálogo, escala 1-5) vive
 * ahora en App\Services\AI\AIResultValidator y AIRiskAnalyzer.
 */
class IaRiesgoService
{
    public function __construct(private readonly AIRiskAnalyzer $analizador)
    {
    }

    public function sugerirParaRiesgo(Usuario $usuario, int $activoId, int $amenazaId, ?int $vulnerabilidadId, ?string $descripcion, ?int $riesgoId = null): array
    {
        return $this->analizador->analizar($usuario, $activoId, $amenazaId, $vulnerabilidadId, $descripcion, $riesgoId);
    }
}
