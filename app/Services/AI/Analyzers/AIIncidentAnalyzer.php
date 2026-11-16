<?php

declare(strict_types=1);

namespace App\Services\AI\Analyzers;

use App\Models\Incidente;
use App\Models\Usuario;
use App\Services\AI\AIResultValidator;
use App\Services\AI\GeminiRespuesta;
use App\Services\AI\SGSIIntelligenceService;
use Illuminate\Support\Str;

/**
 * Analizador de incidentes: naturaleza, clasificación sugerida, grado de
 * certeza (sospecha / indicio / confirmado), impacto CID, contención,
 * recuperación, prevención y borrador de informe técnico.
 *
 * Nunca cambia el estado del incidente. Regla del servidor (no del modelo):
 * sin evidencias registradas, un incidente que aún no está contenido no puede
 * presentarse como «confirmado».
 */
final class AIIncidentAnalyzer
{
    public const CATEGORIAS = [
        'malware', 'phishing', 'acceso_no_autorizado', 'fuga_de_informacion', 'denegacion_de_servicio',
        'perdida_o_robo_de_equipo', 'error_humano', 'falla_tecnica', 'uso_indebido', 'otro',
    ];
    public const CERTEZA = ['sospecha', 'indicio', 'confirmado'];
    public const SEVERIDAD = ['baja', 'media', 'alta', 'critica'];

    public function __construct(
        private readonly SGSIIntelligenceService $asistente,
        private readonly AIResultValidator $validar,
    ) {
    }

    public function analizar(Usuario $usuario, Incidente $incidente): array
    {
        $incidente->loadMissing(['activo', 'evidencias', 'comentarios' => fn ($q) => $q->latest()->limit(5)]);
        $activo = $incidente->activo;
        $evidencias = $incidente->evidencias;

        $datos = "Incidente: {$incidente->titulo}\n"
            . "Estado actual en el sistema: {$incidente->estado->value}\n"
            . 'Detectado: ' . $incidente->fecha_deteccion?->format('Y-m-d H:i') . ' (hace ' . (int) $incidente->fecha_deteccion?->diffInDays(now()) . " días)\n"
            . 'Descripción: ' . ($incidente->descripcion ?: 'sin descripción') . "\n"
            . 'Activo afectado: ' . ($activo
                ? "{$activo->nombre} (tipo {$activo->tipo->value}; C{$activo->confidencialidad}/I{$activo->integridad}/D{$activo->disponibilidad}; criticidad {$activo->criticidad_calculada})"
                : 'no asociado') . "\n"
            . 'Evidencias registradas: ' . $evidencias->count()
            . ($evidencias->isNotEmpty() ? ' (' . $evidencias->pluck('mime_type')->filter()->unique()->implode(', ') . ')' : '') . "\n"
            . 'Notas internas recientes (sin autores): ' . ($incidente->comentarios->isNotEmpty()
                ? "\n" . $incidente->comentarios->map(fn ($c) => '- ' . Str::limit((string) $c->contenido, 300))->implode("\n")
                : 'ninguna');

        $tarea = 'Analiza este incidente de seguridad de la información según ISO/IEC 27001:2022 (A.5.24 a A.5.28) y buenas prácticas de respuesta a incidentes. '
            . 'Clasifícalo de forma SUGERIDA. Indica el grado de certeza: "sospecha" (solo un reporte o percepción), "indicio" (hay señales concretas) '
            . 'o "confirmado" (la evidencia lo demuestra). Nunca afirmes que un ataque ocurrió si la información no lo demuestra. '
            . 'Indica qué información falta para confirmarlo. Propón acciones de contención, recuperación y prevención concretas y priorizadas, '
            . 'hasta 5 controles reales del Anexo A relacionados, y un borrador de informe técnico breve en Markdown '
            . '(secciones: Resumen, Cronología conocida, Análisis, Impacto, Acciones recomendadas, Información pendiente). '
            . 'No inventes hechos, horas, personas ni sistemas que no aparezcan en los datos.';

        $estructura = '{"naturaleza": "...", "categoria_sugerida": "phishing", "nivel_certeza": "sospecha", "justificacion_certeza": "...", '
            . '"severidad_sugerida": "media", "activos_afectados": ["..."], '
            . '"impacto": {"confidencialidad": "...", "integridad": "...", "disponibilidad": "..."}, '
            . '"acciones_contencion": ["..."], "acciones_recuperacion": ["..."], "medidas_preventivas": ["..."], '
            . '"controles_relacionados": [{"codigo": "A.X.X", "nombre": "...", "justificacion": "..."}], '
            . '"informacion_faltante": ["..."], "borrador_informe": "### Resumen\\n..."}';

        $analisis = $this->asistente->analizar(
            $usuario, 'incidente', $incidente, $tarea, $datos, $estructura,
            fn (array $d, GeminiRespuesta $r) => $this->normalizar($d, $r, $incidente, $evidencias->count()),
            ['incidente_uuid' => $incidente->uuid],
        );

        return ['analisis_uuid' => $analisis->uuid] + $analisis->resultado;
    }

    private function normalizar(array $d, GeminiRespuesta $r, Incidente $incidente, int $evidencias): array
    {
        $certeza = $this->validar->opcion($d['nivel_certeza'] ?? null, self::CERTEZA, 'sospecha');
        $nota = null;
        if ($certeza === 'confirmado' && $evidencias === 0 && in_array($incidente->estado->value, ['abierto', 'en_analisis'], true)) {
            $certeza = 'indicio';
            $nota = 'Ajustado por el sistema: no hay evidencias registradas que permitan considerar el incidente como confirmado.';
        }

        [$controles, $descartados] = $this->validar->controles($d['controles_relacionados'] ?? []);
        $impacto = is_array($d['impacto'] ?? null) ? $d['impacto'] : [];

        return [
            'naturaleza' => $this->validar->texto($d['naturaleza'] ?? '', 500),
            'categoria_sugerida' => $this->validar->opcion($d['categoria_sugerida'] ?? null, self::CATEGORIAS, 'otro'),
            'nivel_certeza' => $certeza,
            'justificacion_certeza' => $this->validar->texto($d['justificacion_certeza'] ?? '', 500),
            'nota_certeza' => $nota,
            'severidad_sugerida' => $this->validar->opcion($d['severidad_sugerida'] ?? null, self::SEVERIDAD, 'media'),
            'activos_afectados' => $this->validar->lista($d['activos_afectados'] ?? [], 6, 150),
            'impacto' => [
                'confidencialidad' => $this->validar->texto($impacto['confidencialidad'] ?? '', 400),
                'integridad' => $this->validar->texto($impacto['integridad'] ?? '', 400),
                'disponibilidad' => $this->validar->texto($impacto['disponibilidad'] ?? '', 400),
            ],
            'acciones_contencion' => $this->validar->lista($d['acciones_contencion'] ?? [], 8),
            'acciones_recuperacion' => $this->validar->lista($d['acciones_recuperacion'] ?? [], 8),
            'medidas_preventivas' => $this->validar->lista($d['medidas_preventivas'] ?? [], 8),
            'controles_relacionados' => $controles,
            'controles_descartados' => $descartados,
            'informacion_faltante' => $this->validar->lista($d['informacion_faltante'] ?? []),
            'borrador_informe' => Str::limit(trim(strip_tags((string) ($d['borrador_informe'] ?? ''))), 6000),
            'evidencias_registradas' => $evidencias,
            'modelo' => $r->modelo,
        ];
    }
}
