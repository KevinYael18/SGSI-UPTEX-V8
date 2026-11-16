<?php

declare(strict_types=1);

namespace App\Services\AI\Analyzers;

use App\Models\Auditoria;
use App\Models\Hallazgo;
use App\Models\Usuario;
use App\Services\AI\AIResultValidator;
use App\Services\AI\GeminiRespuesta;
use App\Services\AI\SGSIIntelligenceService;
use Illuminate\Support\Str;

/**
 * Analizador de auditorías: resumen, hallazgos y POSIBLES no conformidades,
 * oportunidades de mejora, acciones correctivas, relación con requisitos o
 * controles (verificados contra el catálogo) y hallazgos recurrentes en el
 * histórico.
 *
 * Nada es definitivo: cada hallazgo propuesto requiere validación del auditor
 * y solo llega al registro oficial si el auditor lo captura en el formulario
 * existente «Registrar hallazgo».
 */
final class AIAuditAnalyzer
{
    public const SEVERIDADES = ['menor', 'mayor', 'critica'];
    public const TIPOS_HALLAZGO = ['no_conformidad_potencial', 'observacion', 'oportunidad_mejora'];

    public function __construct(
        private readonly SGSIIntelligenceService $asistente,
        private readonly AIResultValidator $validar,
    ) {
    }

    public function analizar(Usuario $usuario, Auditoria $auditoria, ?string $notas): array
    {
        $auditoria->loadMissing(['hallazgos.noConformidades.accionesCorrectivas', 'evidencias']);

        $hallazgos = $auditoria->hallazgos->map(function (Hallazgo $h) {
            $ncs = $h->noConformidades->map(function ($nc) {
                $acciones = $nc->accionesCorrectivas->map(fn ($ac) => "acción correctiva {$ac->estado?->value} (compromiso " . $ac->fecha_compromiso?->format('Y-m-d') . ')')->implode('; ');

                return "no conformidad {$nc->estado->value}" . ($acciones !== '' ? " [{$acciones}]" : '');
            })->implode('; ');

            return "- [{$h->severidad->value}] " . Str::limit((string) $h->descripcion, 400) . ($ncs !== '' ? " → {$ncs}" : '');
        })->implode("\n");

        // Histórico para detectar recurrencia (solo descripciones, acotadas).
        $historico = Hallazgo::query()->where('auditoria_id', '!=', $auditoria->id)
            ->latest('id')->limit(30)->get(['severidad', 'descripcion'])
            ->map(fn ($h) => "- [{$h->severidad->value}] " . Str::limit((string) $h->descripcion, 200))->implode("\n");

        $datos = "Auditoría: {$auditoria->alcance}\n"
            . 'Periodo: ' . $auditoria->fecha_inicio?->format('Y-m-d') . ' → ' . ($auditoria->fecha_fin?->format('Y-m-d') ?? 'en curso') . "\n"
            . 'Evidencias adjuntas: ' . $auditoria->evidencias->count() . "\n"
            . "Hallazgos registrados:\n" . ($hallazgos !== '' ? $hallazgos : '- ninguno') . "\n\n"
            . "Notas o resultados aportados por el auditor:\n" . (filled($notas) ? $notas : 'ninguna') . "\n\n"
            . "Hallazgos de auditorías anteriores (para recurrencia):\n" . ($historico !== '' ? $historico : '- sin histórico');

        $tarea = 'Apoya al auditor del SGSI (ISO/IEC 27001:2022 cláusula 9.2 y 10.2). Resume la auditoría, identifica hallazgos y POSIBLES no conformidades '
            . 'a partir de los datos y notas, sugiere oportunidades de mejora y acciones correctivas, y relaciona cada hallazgo con el requisito '
            . '(cláusula, p. ej. "7.5.3") o control del Anexo A (p. ej. "A.5.15") pertinente SOLO si estás seguro. '
            . 'Identifica hallazgos recurrentes SOLO si el histórico lo muestra. Analiza la suficiencia de las evidencias. '
            . 'No declares no conformidades definitivas: son propuestas que el auditor valida. No inventes hechos.';

        $estructura = '{"resumen": "...", "hallazgos_identificados": [{"descripcion": "...", "severidad_sugerida": "menor", '
            . '"tipo": "no_conformidad_potencial", "requisito_relacionado": "A.X.X o cláusula", "justificacion": "..."}], '
            . '"oportunidades_mejora": ["..."], "acciones_correctivas_sugeridas": ["..."], "hallazgos_recurrentes": ["..."], '
            . '"analisis_evidencias": "...", "controles_relacionados": [{"codigo": "A.X.X", "nombre": "...", "justificacion": "..."}]}';

        $analisis = $this->asistente->analizar(
            $usuario, 'auditoria', $auditoria, $tarea, $datos, $estructura,
            fn (array $d, GeminiRespuesta $r) => $this->normalizar($d, $r),
            ['auditoria_id' => $auditoria->id, 'con_notas' => filled($notas)],
        );

        return ['analisis_uuid' => $analisis->uuid] + $analisis->resultado;
    }

    private function normalizar(array $d, GeminiRespuesta $r): array
    {
        $descartadas = 0;
        $hallazgos = collect(is_array($d['hallazgos_identificados'] ?? null) ? $d['hallazgos_identificados'] : [])
            ->filter(fn ($h) => is_array($h) && filled($h['descripcion'] ?? null))
            ->take(8)
            ->map(function (array $h) use (&$descartadas) {
                $referencia = $this->validar->referencia($h['requisito_relacionado'] ?? null);
                if ($referencia === null && filled($h['requisito_relacionado'] ?? null)) {
                    $descartadas++;
                }

                return [
                    'descripcion' => $this->validar->texto($h['descripcion'], 800),
                    'severidad_sugerida' => $this->validar->opcion($h['severidad_sugerida'] ?? null, self::SEVERIDADES, 'menor'),
                    'tipo' => $this->validar->opcion($h['tipo'] ?? null, self::TIPOS_HALLAZGO, 'observacion'),
                    'requisito_relacionado' => $referencia,
                    'justificacion' => $this->validar->texto($h['justificacion'] ?? '', 400),
                    'requiere_validacion' => true,
                ];
            })->values()->all();

        [$controles, $descartados] = $this->validar->controles($d['controles_relacionados'] ?? []);

        return [
            'resumen' => $this->validar->texto($d['resumen'] ?? '', 1200),
            'hallazgos_identificados' => $hallazgos,
            'oportunidades_mejora' => $this->validar->lista($d['oportunidades_mejora'] ?? []),
            'acciones_correctivas_sugeridas' => $this->validar->lista($d['acciones_correctivas_sugeridas'] ?? []),
            'hallazgos_recurrentes' => $this->validar->lista($d['hallazgos_recurrentes'] ?? []),
            'analisis_evidencias' => $this->validar->texto($d['analisis_evidencias'] ?? '', 600),
            'controles_relacionados' => $controles,
            'referencias_descartadas' => $descartadas + $descartados,
            'modelo' => $r->modelo,
        ];
    }
}
