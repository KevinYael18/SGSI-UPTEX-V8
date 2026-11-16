<?php

declare(strict_types=1);

namespace App\Services\AI\Analyzers;

use App\Models\Activo;
use App\Models\Amenaza;
use App\Models\Riesgo;
use App\Models\Usuario;
use App\Models\Vulnerabilidad;
use App\Repositories\Contracts\ActivoRepositoryInterface;
use App\Services\AI\AIResultValidator;
use App\Services\AI\GeminiRespuesta;
use App\Services\AI\SGSIIntelligenceService;
use App\Support\ImpactoRiesgoCalculator;
use App\Support\MatrizRiesgoResolver;
use App\Support\ProbabilidadRiesgoCalculator;

/**
 * Analizador de riesgos de SGSI Intelligence Assistant. Evoluciona el
 * «Asistente de IA» original del formulario de riesgos (IaRiesgoService):
 * conserva su contrato (controles validados contra el catálogo, probabilidad
 * e impacto 1-5, justificación) y agrega amenazas, vulnerabilidades,
 * consecuencias, análisis CID, tratamiento, acciones preventivas y riesgo
 * residual.
 *
 * Metodología intacta: la escala sigue siendo 1-5 y el NIVEL nunca lo decide
 * la IA, lo resuelve MatrizRiesgoResolver con la matriz configurada. Los
 * valores de la metodología (ProbabilidadRiesgoCalculator e
 * ImpactoRiesgoCalculator) se entregan al modelo como referencia y se
 * devuelven junto a la propuesta para compararlas.
 */
final class AIRiskAnalyzer
{
    public const TRATAMIENTOS = ['mitigar', 'transferir', 'evitar', 'aceptar'];

    public function __construct(
        private readonly SGSIIntelligenceService $asistente,
        private readonly AIResultValidator $validar,
        private readonly ActivoRepositoryInterface $activos,
        private readonly MatrizRiesgoResolver $matriz,
        private readonly ProbabilidadRiesgoCalculator $probabilidad,
        private readonly ImpactoRiesgoCalculator $impacto,
    ) {
    }

    public function analizar(Usuario $usuario, int $activoId, int $amenazaId, ?int $vulnerabilidadId, ?string $descripcion, ?int $riesgoId = null): array
    {
        /** @var Activo $activo */
        $activo = $this->activos->encontrarOFallar($activoId);
        $amenaza = Amenaza::query()->findOrFail($amenazaId);
        $vulnerabilidad = $vulnerabilidadId !== null ? Vulnerabilidad::query()->findOrFail($vulnerabilidadId) : null;

        $metodologia = [
            'probabilidad' => $vulnerabilidad ? $this->probabilidad->calcular($activo, $amenaza, $vulnerabilidad) : null,
            'impacto' => $this->impacto->calcular($activo),
        ];

        $controlesActivo = $activo->controlesIso()->get()
            ->map(fn ($c) => "{$c->codigo} ({$c->estado_implementacion?->value})")->implode(', ');
        $otrosRiesgos = Riesgo::query()->where('activo_id', $activo->id)->when($riesgoId, fn ($q) => $q->where('id', '!=', $riesgoId))
            ->selectRaw('nivel_calculado, COUNT(*) as n')->groupBy('nivel_calculado')->pluck('n', 'nivel_calculado')
            ->map(fn ($n, $k) => "{$k}: {$n}")->implode(', ');

        $datos = "Activo: {$activo->nombre} (tipo: {$activo->tipo->value}" . ($activo->subtipo ? ", subtipo: {$activo->subtipo}" : '') . ').'
            . "\nValoración del activo (1-5): confidencialidad {$activo->confidencialidad}, integridad {$activo->integridad}, disponibilidad {$activo->disponibilidad}; criticidad calculada {$activo->criticidad_calculada}."
            . "\nControles del Anexo A vinculados al activo: " . ($controlesActivo !== '' ? $controlesActivo : 'ninguno') . '.'
            . "\nAmenaza: {$amenaza->nombre} (categoría: {$amenaza->categoria})."
            . "\nVulnerabilidad: " . ($vulnerabilidad ? "{$vulnerabilidad->nombre} (categoría: {$vulnerabilidad->categoria})" : 'no indicada') . '.'
            . "\nDescripción adicional del usuario: " . (filled($descripcion) ? $descripcion : 'ninguna') . '.'
            . "\nOtros riesgos ya registrados para este activo: " . ($otrosRiesgos !== '' ? $otrosRiesgos : 'ninguno') . '.'
            . "\nMetodología del sistema (referencia): probabilidad calculada " . ($metodologia['probabilidad'] ?? 'no disponible sin vulnerabilidad')
            . ", impacto calculado {$metodologia['impacto']} (impacto = criticidad del activo; probabilidad = base por categoría de amenaza ajustada por los controles del activo).";

        $tarea = 'Analiza este riesgo de seguridad de la información de una universidad según ISO/IEC 27001:2022 e ISO/IEC 27005. '
            . 'Usa EXCLUSIVAMENTE la escala del sistema: enteros de 1 (muy baja) a 5 (muy alta) para probabilidad e impacto. '
            . 'Parte de los valores de la metodología del sistema y explica si propones otro valor y por qué. '
            . 'NO calcules el nivel del riesgo: el sistema lo obtiene de su matriz configurada. '
            . 'Los controles deben ser códigos reales del Anexo A (A.5.1 a A.8.34), máximo 5. '
            . 'El tratamiento debe ser uno de: mitigar, transferir, evitar, aceptar. '
            . 'Propón riesgo residual (probabilidad e impacto tras aplicar los controles) SOLO si la información es suficiente; si no, usa null.';

        $estructura = '{"amenazas_identificadas": ["..."], "vulnerabilidades_identificadas": ["..."], "consecuencias": ["..."], '
            . '"analisis_cid": {"confidencialidad": "...", "integridad": "...", "disponibilidad": "..."}, '
            . '"probabilidad_sugerida": 1, "impacto_sugerido": 1, "justificacion_nivel": "...", '
            . '"tratamiento_sugerido": "mitigar", "justificacion_tratamiento": "...", '
            . '"controles_sugeridos": [{"codigo": "A.X.X", "nombre": "...", "justificacion": "..."}], '
            . '"acciones_preventivas": ["..."], '
            . '"riesgo_residual": {"probabilidad": 1, "impacto": 1, "justificacion": "..."}, '
            . '"recomendaciones": ["..."]}';

        $analisis = $this->asistente->analizar(
            $usuario,
            'riesgo',
            $riesgoId !== null ? Riesgo::query()->find($riesgoId) : $activo,
            $tarea,
            $datos,
            $estructura,
            fn (array $d, GeminiRespuesta $r) => $this->normalizar($d, $r, $metodologia),
            ['activo_id' => $activo->id, 'amenaza_id' => $amenaza->id, 'vulnerabilidad_id' => $vulnerabilidad?->id, 'riesgo_id' => $riesgoId],
        );

        return ['analisis_uuid' => $analisis->uuid] + $analisis->resultado;
    }

    private function normalizar(array $d, GeminiRespuesta $r, array $metodologia): array
    {
        $probabilidad = $this->validar->escala($d['probabilidad_sugerida'] ?? null);
        $impacto = $this->validar->escala($d['impacto_sugerido'] ?? null);
        if ($probabilidad === null || $impacto === null) {
            throw new \App\Exceptions\IaNoDisponibleException();
        }

        [$controles, $descartados] = $this->validar->controles($d['controles_sugeridos'] ?? []);

        $residual = null;
        if (is_array($d['riesgo_residual'] ?? null)) {
            $pr = $this->validar->escala($d['riesgo_residual']['probabilidad'] ?? null);
            $ir = $this->validar->escala($d['riesgo_residual']['impacto'] ?? null);
            if ($pr !== null && $ir !== null) {
                $residual = [
                    'probabilidad' => $pr,
                    'impacto' => $ir,
                    'nivel' => $this->nivel($pr, $ir),
                    'justificacion' => $this->validar->texto($d['riesgo_residual']['justificacion'] ?? ''),
                ];
            }
        }

        $cid = is_array($d['analisis_cid'] ?? null) ? $d['analisis_cid'] : [];

        return [
            // Contrato original del «Asistente de IA» (compatibilidad).
            'controles_sugeridos' => $controles,
            'probabilidad_sugerida' => $probabilidad,
            'impacto_sugerido' => $impacto,
            'justificacion_nivel' => $this->validar->texto($d['justificacion_nivel'] ?? ''),
            'controles_descartados' => $descartados,
            'modelo' => $r->modelo,
            // Evolución SGSI Intelligence Assistant.
            'nivel_sugerido' => $this->nivel($probabilidad, $impacto),
            'metodologia' => $metodologia + ['nivel' => $metodologia['probabilidad'] !== null ? $this->nivel($metodologia['probabilidad'], $metodologia['impacto']) : null],
            'amenazas_identificadas' => $this->validar->lista($d['amenazas_identificadas'] ?? []),
            'vulnerabilidades_identificadas' => $this->validar->lista($d['vulnerabilidades_identificadas'] ?? []),
            'consecuencias' => $this->validar->lista($d['consecuencias'] ?? []),
            'analisis_cid' => [
                'confidencialidad' => $this->validar->texto($cid['confidencialidad'] ?? '', 400),
                'integridad' => $this->validar->texto($cid['integridad'] ?? '', 400),
                'disponibilidad' => $this->validar->texto($cid['disponibilidad'] ?? '', 400),
            ],
            'tratamiento_sugerido' => $this->validar->opcion($d['tratamiento_sugerido'] ?? null, self::TRATAMIENTOS),
            'justificacion_tratamiento' => $this->validar->texto($d['justificacion_tratamiento'] ?? ''),
            'acciones_preventivas' => $this->validar->lista($d['acciones_preventivas'] ?? []),
            'riesgo_residual' => $residual,
            'recomendaciones' => $this->validar->lista($d['recomendaciones'] ?? []),
        ];
    }

    /** Nivel según la matriz configurada del sistema (null si la combinación no está configurada). */
    private function nivel(int $probabilidad, int $impacto): ?string
    {
        try {
            return $this->matriz->resolver($probabilidad, $impacto)->value;
        } catch (\Throwable) {
            return null;
        }
    }
}
