<?php

declare(strict_types=1);

namespace App\Services\AI\Analyzers;

use App\Exceptions\IaDocumentoNoProcesableException;
use App\Models\Documento;
use App\Models\Usuario;
use App\Services\AI\AIDocumentTextExtractor;
use App\Services\AI\AIPromptGuard;
use App\Services\AI\AIResultValidator;
use App\Services\AI\GeminiRespuesta;
use App\Services\AI\SGSIIntelligenceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Análisis de documentos del SGSI (versión vigente): resumen, posibles
 * inconsistencias, información aparentemente faltante, mejoras, controles
 * relacionados, necesidad de actualización y respuesta a una pregunta
 * concreta sobre el documento.
 *
 * Nunca modifica el documento: las mejoras son propuestas para revisión. El
 * análisis NO certifica cumplimiento de ISO/IEC 27001 (advertencia fija del
 * servidor). El contenido del documento es dato, no instrucción: si trae
 * texto que intenta dirigir a la IA, se advierte al usuario.
 */
final class AIDocumentAnalyzer
{
    public const TIPOS = ['politica', 'procedimiento', 'instructivo', 'registro', 'plan', 'manual', 'otro'];
    private const MAX_CARACTERES_ANALISIS = 60000;
    public const ADVERTENCIA = 'Este análisis es una revisión asistida y no certifica el cumplimiento de ISO/IEC 27001:2022; '
        . 'la conformidad solo puede determinarse mediante una auditoría. Las mejoras propuestas deben revisarse antes de incorporarse en una nueva versión.';

    public function __construct(
        private readonly SGSIIntelligenceService $asistente,
        private readonly AIResultValidator $validar,
        private readonly AIDocumentTextExtractor $extractor,
        private readonly AIPromptGuard $guardia,
    ) {
    }

    public function analizar(Usuario $usuario, Documento $documento, ?string $pregunta): array
    {
        $version = $documento->versiones()->orderByDesc('numero_version')->first();
        if ($version === null) {
            throw new IaDocumentoNoProcesableException('El documento no tiene versiones cargadas.');
        }

        $texto = $this->textoDeVersion($version->path_storage, $usuario->id);
        $sospechoso = $this->guardia->fragmentoSospechoso($texto);
        $recortado = mb_strlen($texto) > self::MAX_CARACTERES_ANALISIS;

        $datos = "Metadatos del documento:\n"
            . "- Título: {$documento->titulo}\n"
            . "- Estado: {$documento->estado->value}; versión vigente: {$version->numero_version}\n"
            . '- Vigencia de la versión: ' . ($version->fecha_expiracion?->format('Y-m-d') ?? 'sin vencimiento') . "\n"
            . '- Publicado: ' . ($documento->publicado_en?->format('Y-m-d') ?? 'no publicado') . "\n"
            . '- Última actualización: ' . $documento->updated_at?->format('Y-m-d') . "\n\n"
            . "<documento>\n" . mb_substr($texto, 0, self::MAX_CARACTERES_ANALISIS) . "\n</documento>"
            . ($recortado ? "\n(El documento se recortó por su extensión; analiza solo lo incluido.)" : '');

        $tarea = 'Revisa este documento del SGSI de una universidad. Resúmelo, identifica su tipo, posibles inconsistencias internas, '
            . 'información que aparentemente falta para un documento de su tipo bajo ISO/IEC 27001:2022 (p. ej. alcance, responsables, revisión, '
            . 'aprobación, vigencia), mejoras recomendadas y hasta 5 controles reales del Anexo A con los que se relaciona. '
            . 'Indica si parece necesitar actualización y por qué (fechas, referencias obsoletas, vigencia). '
            . 'No afirmes que el documento "cumple" la norma. '
            . (filled($pregunta)
                ? 'Además responde esta pregunta del usuario usando SOLO el contenido del documento (si no está en el documento, dilo): «' . mb_substr((string) $pregunta, 0, 500) . '».'
                : 'No hay pregunta del usuario: usa null en "respuesta_pregunta".');

        $estructura = '{"resumen": "...", "tipo_documento": "politica", "inconsistencias": ["..."], "informacion_faltante": ["..."], '
            . '"mejoras_recomendadas": ["..."], "controles_relacionados": [{"codigo": "A.X.X", "nombre": "...", "justificacion": "..."}], '
            . '"necesita_actualizacion": false, "motivo_actualizacion": "...", "respuesta_pregunta": null}';

        $analisis = $this->asistente->analizar(
            $usuario, 'documento', $documento, $tarea, $datos, $estructura,
            fn (array $d, GeminiRespuesta $r) => $this->normalizar($d, $r, $version->numero_version, $sospechoso, $recortado, filled($pregunta)),
            ['documento_uuid' => $documento->uuid, 'version' => $version->numero_version, 'con_pregunta' => filled($pregunta)],
        );

        return ['analisis_uuid' => $analisis->uuid] + $analisis->resultado;
    }

    /** El texto extraído se guarda en caché por el hash del archivo (un PDF no se re-extrae en cada análisis). */
    private function textoDeVersion(string $ruta, int $usuarioId): string
    {
        $disco = Storage::disk(config('filesystems.default'));
        if (!$disco->exists($ruta)) {
            throw new IaDocumentoNoProcesableException('El archivo de la versión vigente no se encuentra en el almacenamiento.');
        }
        $contenido = (string) $disco->get($ruta);

        return Cache::remember('ia-doc-texto:' . hash('sha256', $contenido), now()->addDay(),
            fn () => $this->extractor->extraer($contenido, basename($ruta), $disco->mimeType($ruta) ?: null, $usuarioId));
    }

    private function normalizar(array $d, GeminiRespuesta $r, int $version, bool $sospechoso, bool $recortado, bool $conPregunta): array
    {
        [$controles, $descartados] = $this->validar->controles($d['controles_relacionados'] ?? []);

        return [
            'resumen' => $this->validar->texto($d['resumen'] ?? '', 1500),
            'tipo_documento' => $this->validar->opcion($d['tipo_documento'] ?? null, self::TIPOS, 'otro'),
            'inconsistencias' => $this->validar->lista($d['inconsistencias'] ?? []),
            'informacion_faltante' => $this->validar->lista($d['informacion_faltante'] ?? []),
            'mejoras_recomendadas' => $this->validar->lista($d['mejoras_recomendadas'] ?? [], 8),
            'controles_relacionados' => $controles,
            'controles_descartados' => $descartados,
            'necesita_actualizacion' => ($d['necesita_actualizacion'] ?? false) === true,
            'motivo_actualizacion' => $this->validar->texto($d['motivo_actualizacion'] ?? '', 500),
            'respuesta_pregunta' => $conPregunta ? $this->validar->texto($d['respuesta_pregunta'] ?? '', 2000) : null,
            'version_analizada' => $version,
            'documento_recortado' => $recortado,
            'contiene_instrucciones_sospechosas' => $sospechoso,
            'advertencia' => self::ADVERTENCIA,
            'modelo' => $r->modelo,
        ];
    }
}
