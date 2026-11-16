<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Exceptions\IaNoDisponibleException;
use App\Models\IaDocumentoConocimiento;
use App\Models\IaFragmentoDocumento;
use App\Models\Usuario;
use App\Support\Bitacora;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Base de conocimiento (RAG) de SGSI Intelligence Assistant.
 *
 * Ingesta:  archivo autorizado → texto → fragmentos con solapamiento →
 *           revisión anti-inyección por fragmento → embeddings (Gemini) → MySQL.
 * Consulta: embedding de la pregunta → similitud coseno contra los fragmentos
 *           de documentos activos → top-N por encima del umbral. Si no hay
 *           embeddings disponibles se usa una búsqueda por palabras clave.
 *
 * Los fragmentos recuperados se entregan al modelo como REFERENCIA, nunca
 * como instrucciones; los fragmentos marcados como sospechosos se excluyen.
 * El archivo original no se conserva: solo su texto fragmentado y su hash.
 */
final class AIKnowledgeBase
{
    private const EXTENSIONES = ['pdf', 'docx', 'xlsx', 'xlsm', 'xls', 'txt', 'md', 'csv'];

    public function __construct(
        private readonly GeminiService $gemini,
        private readonly AIDocumentTextExtractor $extractor,
        private readonly AIPromptGuard $guardia,
    ) {
    }

    public static function extensionesPermitidas(): array
    {
        return self::EXTENSIONES;
    }

    public function cargar(UploadedFile $archivo, string $titulo, string $categoria, ?string $descripcion, Usuario $usuario): IaDocumentoConocimiento
    {
        $contenido = (string) file_get_contents($archivo->getRealPath());

        $documento = IaDocumentoConocimiento::query()->create([
            'titulo' => $titulo,
            'categoria' => $categoria,
            'descripcion' => $descripcion,
            'nombre_archivo' => mb_substr($archivo->getClientOriginalName(), 0, 255),
            'mime_type' => $archivo->getMimeType(),
            'hash_sha256' => hash('sha256', $contenido),
            'estado' => 'procesando',
            'subido_por' => $usuario->id,
        ]);

        Bitacora::registrar('ia_documentos_conocimiento', $documento->id, $usuario->id, 'create', [
            'evento' => 'ia_conocimiento_cargado', 'titulo' => $titulo, 'categoria' => $categoria,
        ]);

        $this->procesar($documento, $contenido, $usuario->id);

        return $documento->refresh();
    }

    /** Extrae, fragmenta e indexa. Deja el documento en estado listo o error (nunca a medias). */
    public function procesar(IaDocumentoConocimiento $documento, string $contenido, ?int $usuarioId): void
    {
        try {
            $texto = $this->extractor->extraer($contenido, (string) $documento->nombre_archivo, $documento->mime_type, $usuarioId);
        } catch (\Throwable $e) {
            $documento->update(['estado' => 'error', 'error' => mb_substr($e->getMessage(), 0, 255)]);

            return;
        }

        $fragmentos = $this->fragmentar($texto);
        $vectores = [];
        $avisoEmbeddings = null;
        try {
            $vectores = $this->gemini->embeber(array_column($fragmentos, 'texto'), 'RETRIEVAL_DOCUMENT', $usuarioId);
        } catch (IaNoDisponibleException) {
            // Sin embeddings el documento sigue siendo útil vía búsqueda por palabras clave.
            $avisoEmbeddings = 'Indexado sin embeddings: se usará búsqueda por palabras clave.';
        }

        DB::transaction(function () use ($documento, $fragmentos, $vectores, $avisoEmbeddings) {
            $documento->fragmentos()->delete();
            $sospechosos = 0;
            foreach ($fragmentos as $i => $f) {
                $sospechoso = $this->guardia->fragmentoSospechoso($f['texto']);
                $sospechosos += $sospechoso ? 1 : 0;
                IaFragmentoDocumento::query()->create([
                    'documento_id' => $documento->id,
                    'orden' => $i,
                    'contenido' => $f['texto'],
                    'embedding' => isset($vectores[$i]) && $vectores[$i] !== [] ? IaFragmentoDocumento::empaquetar($vectores[$i]) : null,
                    'modelo_embedding' => isset($vectores[$i]) ? config('sgsi.ia.gemini.embedding_model') : null,
                    'sospechoso' => $sospechoso,
                ]);
            }
            $documento->update([
                'estado' => 'listo',
                'error' => $avisoEmbeddings,
                'total_fragmentos' => count($fragmentos),
                'fragmentos_sospechosos' => $sospechosos,
            ]);
        });
    }

    /**
     * Fragmentos por párrafos con tamaño objetivo y solapamiento.
     *
     * @return list<array{texto: string}>
     */
    public function fragmentar(string $texto): array
    {
        $tamano = max(300, (int) config('sgsi.ia.rag.tamano_fragmento'));
        $solape = max(0, min((int) config('sgsi.ia.rag.solapamiento'), intdiv($tamano, 2)));

        $parrafos = preg_split("/\n\s*\n/u", $texto) ?: [$texto];
        $fragmentos = [];
        $actual = '';
        foreach ($parrafos as $parrafo) {
            $parrafo = trim($parrafo);
            if ($parrafo === '') {
                continue;
            }
            // Párrafos enormes: se cierra lo acumulado y se cortan en ventanas con solapamiento.
            if (mb_strlen($parrafo) > $tamano) {
                if (trim($actual) !== '') {
                    $fragmentos[] = trim($actual);
                    $actual = '';
                }
                $paso = $tamano - $solape;
                for ($i = 0; $i < mb_strlen($parrafo); $i += $paso) {
                    $fragmentos[] = mb_substr($parrafo, $i, $tamano);
                }
                continue;
            }
            if (mb_strlen($actual) + mb_strlen($parrafo) + 1 > $tamano && $actual !== '') {
                $fragmentos[] = trim($actual);
                $actual = mb_substr($actual, -$solape);
            }
            $actual .= "\n" . $parrafo;
        }
        if (trim($actual) !== '') {
            $fragmentos[] = trim($actual);
        }

        return array_map(fn ($t) => ['texto' => $t], array_values(array_filter($fragmentos, fn ($t) => mb_strlen($t) >= 20)));
    }

    /**
     * @return list<array{documento: string, categoria: string, contenido: string, similitud: float}>
     */
    public function buscar(string $consulta, ?int $usuarioId = null, ?int $limite = null): array
    {
        $limite ??= (int) config('sgsi.ia.rag.fragmentos_contexto');

        $hayDocumentos = IaDocumentoConocimiento::query()->where('activo', true)->where('estado', 'listo')->exists();
        if (!$hayDocumentos) {
            return [];
        }

        $fragmentos = IaFragmentoDocumento::query()
            ->with('documento:id,titulo,categoria')
            ->where('sospechoso', false)
            ->whereHas('documento', fn ($q) => $q->where('activo', true)->where('estado', 'listo'))
            ->get(['id', 'documento_id', 'orden', 'contenido', 'embedding']);

        $conVector = $fragmentos->filter(fn ($f) => $f->embedding !== null);
        $resultados = [];

        if ($conVector->isNotEmpty() && $this->gemini->configurado()) {
            try {
                $consultaVec = $this->gemini->embeber([$consulta], 'RETRIEVAL_QUERY', $usuarioId)[0] ?? [];
                $minimo = (float) config('sgsi.ia.rag.similitud_minima');
                foreach ($conVector as $f) {
                    $sim = $this->coseno($consultaVec, $f->vector() ?? []);
                    if ($sim >= $minimo) {
                        $resultados[] = [$f, $sim];
                    }
                }
            } catch (IaNoDisponibleException $e) {
                Log::info('IA: búsqueda semántica no disponible; se usa palabras clave.');
            }
        }

        if ($resultados === []) {
            $resultados = $this->porPalabrasClave($consulta, $fragmentos);
        }

        usort($resultados, fn ($a, $b) => $b[1] <=> $a[1]);

        return array_map(fn ($r) => [
            'documento' => $r[0]->documento->titulo,
            'categoria' => $r[0]->documento->categoria,
            'contenido' => $r[0]->contenido,
            'similitud' => round($r[1], 3),
        ], array_slice($resultados, 0, $limite));
    }

    /** Puntaje simple por términos compartidos (respaldo sin embeddings). */
    private function porPalabrasClave(string $consulta, $fragmentos): array
    {
        $terminos = array_values(array_filter(
            array_unique(explode(' ', (string) preg_replace('/[^a-z0-9\. ]/', ' ', AIPromptGuard::normalizar($consulta)))),
            fn ($t) => mb_strlen($t) >= 4
        ));
        if ($terminos === []) {
            return [];
        }

        $resultados = [];
        foreach ($fragmentos as $f) {
            $texto = AIPromptGuard::normalizar($f->contenido);
            $coinciden = count(array_filter($terminos, fn ($t) => str_contains($texto, $t)));
            $puntaje = $coinciden / count($terminos);
            if ($coinciden >= 2 && $puntaje >= 0.3) {
                $resultados[] = [$f, $puntaje];
            }
        }

        return $resultados;
    }

    private function coseno(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $punto = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $punto += $a[$i] * $b[$i];
        }

        return $punto; // ambos vectores están normalizados (norma 1)
    }
}
