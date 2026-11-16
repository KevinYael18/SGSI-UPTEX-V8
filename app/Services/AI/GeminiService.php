<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Exceptions\IaNoDisponibleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ÚNICA conexión de GSI UPTex con Google Gemini (proveedor interno de SGSI
 * Intelligence Assistant). Evoluciona la conexión que vivía en
 * IaRiesgoService: misma API, misma clave, mismas precauciones (clave en
 * cabecera, timeout, reintento ante 503), ahora reutilizable por todos los
 * módulos y con:
 *
 *  - Modelos de respaldo: si el principal está saturado (503/429), no existe
 *    (404) o no responde a tiempo, se intenta con el siguiente configurado.
 *  - Cortacircuitos: un modelo que acaba de fallar así se deja al final de la
 *    fila por unos minutos (no se espera su timeout en cada solicitud).
 *  - Presupuesto de tiempo total por solicitud (principal + respaldos).
 *  - Clasificación de errores: clave inválida, cuota, saturación, bloqueo.
 *  - Control de consumo: presupuesto diario antes de llamar y registro de
 *    tokens/latencia de cada llamada (AIUsageTracker).
 *  - Embeddings para la base de conocimiento (RAG).
 *
 * Nadie más en el sistema debe llamar a generativelanguage.googleapis.com.
 */
final class GeminiService
{
    private const BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const ESPERA_REINTENTO_MS = 800;
    private const CLAVE_DEGRADADO = 'ia-modelo-degradado:';

    public function __construct(private readonly AIUsageTracker $uso)
    {
    }

    public function configurado(): bool
    {
        return filled(config('sgsi.ia.gemini.key'));
    }

    public function modeloPrincipal(): string
    {
        return (string) config('sgsi.ia.gemini.model');
    }

    public function generar(GeminiPeticion $peticion): GeminiRespuesta
    {
        $config = config('sgsi.ia.gemini');
        if (blank($config['key'] ?? null)) {
            Log::warning('IA: GEMINI_API_KEY no está configurada.');
            throw new IaNoDisponibleException();
        }

        $this->uso->verificarPresupuesto($peticion->usuarioId);

        $modelos = $peticion->modelo !== null
            ? [$peticion->modelo]
            : $this->ordenarPorDisponibilidad(array_values(array_unique(array_merge([$config['model']], $config['fallback_models'] ?? []))));

        // Presupuesto de tiempo TOTAL de la llamada (principal + respaldos): evita
        // que el proxy o PHP corten la petición mientras se prueban modelos.
        $limite = hrtime(true) + (int) ($config['presupuesto_segundos'] ?? 50) * 1_000_000_000;

        $ultimoError = 'DESCONOCIDO';
        foreach ($modelos as $modelo) {
            $restante = (int) floor(($limite - hrtime(true)) / 1_000_000_000);
            if ($restante < 5) {
                $ultimoError = $ultimoError === 'DESCONOCIDO' ? 'TIMEOUT' : $ultimoError;
                break;
            }
            $inicio = hrtime(true);
            try {
                $respuesta = $this->llamar($modelo, $this->cuerpo($peticion, $modelo), min((int) $config['timeout'], $restante));
            } catch (ConnectionException $e) {
                $ultimoError = 'TIMEOUT';
                $this->marcarDegradado($modelo, $ultimoError);
                Log::warning('IA: sin conexión o tiempo agotado con Gemini', ['modelo' => $modelo, 'error' => $e->getMessage()]);
                $this->uso->registrar($peticion->operacion, $peticion->usuarioId, $modelo, exito: false, codigoError: $ultimoError, latenciaMs: $this->ms($inicio), categoria: $peticion->categoria);
                continue;
            }

            $latencia = $this->ms($inicio);

            if ($respuesta->failed()) {
                $ultimoError = $this->codigoError($respuesta);
                $this->marcarDegradado($modelo, $ultimoError);
                Log::warning('IA: Gemini respondió con error', ['status' => $respuesta->status(), 'codigo' => $ultimoError, 'modelo' => $modelo]);
                $this->uso->registrar($peticion->operacion, $peticion->usuarioId, $modelo, exito: false, codigoError: $ultimoError, latenciaMs: $latencia, categoria: $peticion->categoria);

                // Una clave inválida o una petición mal formada fallarán igual con
                // cualquier modelo: no se gasta tiempo en los de respaldo.
                if (in_array($ultimoError, ['API_KEY_INVALIDA', 'PETICION_INVALIDA'], true)) {
                    break;
                }
                continue;
            }

            $texto = $this->textoDe($respuesta);
            $tokensEntrada = (int) $respuesta->json('usageMetadata.promptTokenCount', 0);
            $tokensSalida = (int) $respuesta->json('usageMetadata.candidatesTokenCount', 0)
                + (int) $respuesta->json('usageMetadata.thoughtsTokenCount', 0);
            $finish = $respuesta->json('candidates.0.finishReason');

            if ($texto === '') {
                $ultimoError = $respuesta->json('promptFeedback.blockReason') !== null || $finish === 'SAFETY' ? 'BLOQUEADO' : 'RESPUESTA_VACIA';
                Log::warning('IA: respuesta vacía de Gemini', ['finish_reason' => $finish, 'modelo' => $modelo]);
                $this->uso->registrar($peticion->operacion, $peticion->usuarioId, $modelo, $tokensEntrada, $tokensSalida, false, $ultimoError, $latencia, $peticion->categoria);
                continue;
            }

            $this->uso->registrar($peticion->operacion, $peticion->usuarioId, $modelo, $tokensEntrada, $tokensSalida, true, null, $latencia, $peticion->categoria);

            return new GeminiRespuesta($texto, $modelo, $tokensEntrada, $tokensSalida, $latencia, $finish);
        }

        throw new IaNoDisponibleException(match ($ultimoError) {
            'API_KEY_INVALIDA' => 'SGSI Intelligence Assistant no está disponible: la configuración del proveedor no es válida. Avise al administrador.',
            'CUOTA_EXCEDIDA' => 'SGSI Intelligence Assistant alcanzó el límite de uso del proveedor. Intente más tarde.',
            'SATURADO', 'TIMEOUT' => 'SGSI Intelligence Assistant está recibiendo mucha demanda en este momento. Intente de nuevo en unos segundos.',
            default => 'El servicio de IA no está disponible en este momento.',
        });
    }

    /**
     * Cortacircuitos: un modelo que acaba de fallar por saturación, cuota o
     * tiempo agotado pasa al final de la fila durante unos minutos, para no
     * esperar su timeout en cada solicitud. Nunca se descarta del todo: si
     * todos están degradados se intentan en el orden configurado.
     *
     * @param list<string> $modelos
     * @return list<string>
     */
    private function ordenarPorDisponibilidad(array $modelos): array
    {
        $sanos = array_values(array_filter($modelos, fn ($m) => !Cache::has(self::CLAVE_DEGRADADO . $m)));
        $degradados = array_values(array_diff($modelos, $sanos));

        return [...$sanos, ...$degradados];
    }

    private function marcarDegradado(string $modelo, string $codigo): void
    {
        $segundos = match ($codigo) {
            'SATURADO', 'TIMEOUT', 'CUOTA_EXCEDIDA' => 120,
            'MODELO_NO_DISPONIBLE' => 3600,
            default => 0,
        };
        if ($segundos > 0) {
            Cache::put(self::CLAVE_DEGRADADO . $modelo, $codigo, $segundos);
        }
    }

    /**
     * Embeddings normalizados (norma 1) para una lista de textos.
     *
     * @param list<string> $textos
     * @return list<list<float>>
     */
    public function embeber(array $textos, string $tarea, ?int $usuarioId = null): array
    {
        $config = config('sgsi.ia.gemini');
        if (blank($config['key'] ?? null)) {
            throw new IaNoDisponibleException();
        }
        if ($textos === []) {
            return [];
        }

        $modelo = $config['embedding_model'];
        $vectores = [];
        foreach (array_chunk($textos, 50) as $lote) {
            $inicio = hrtime(true);
            try {
                $respuesta = $this->cliente((int) $config['timeout'])
                    ->post(self::BASE . "{$modelo}:batchEmbedContents", [
                        'requests' => array_map(fn (string $t) => [
                            'model' => "models/{$modelo}",
                            'content' => ['parts' => [['text' => mb_substr($t, 0, 8000)]]],
                            'taskType' => $tarea,
                            'outputDimensionality' => $config['embedding_dims'],
                        ], $lote),
                    ]);
            } catch (ConnectionException $e) {
                $this->uso->registrar('embedding', $usuarioId, $modelo, exito: false, codigoError: 'TIMEOUT', latenciaMs: $this->ms($inicio));
                throw new IaNoDisponibleException();
            }

            if ($respuesta->failed()) {
                $this->uso->registrar('embedding', $usuarioId, $modelo, exito: false, codigoError: $this->codigoError($respuesta), latenciaMs: $this->ms($inicio));
                throw new IaNoDisponibleException();
            }

            $tokens = (int) array_sum(array_map(fn (string $t) => (int) ceil(mb_strlen($t) / 4), $lote));
            $this->uso->registrar('embedding', $usuarioId, $modelo, $tokens, 0, true, null, $this->ms($inicio));

            foreach ((array) $respuesta->json('embeddings', []) as $item) {
                $vectores[] = $this->normalizar(array_map('floatval', (array) ($item['values'] ?? [])));
            }
        }

        return $vectores;
    }

    private function cuerpo(GeminiPeticion $p, string $modelo): array
    {
        $contenidos = [];
        foreach ($p->turnos as $i => $turno) {
            $partes = [['text' => $turno['texto']]];
            // Los adjuntos (p. ej. un PDF a extraer) van en el último turno del usuario.
            if ($i === array_key_last($p->turnos)) {
                foreach ($p->adjuntos as $adjunto) {
                    $partes[] = ['inline_data' => ['mime_type' => $adjunto['mime'], 'data' => $adjunto['datos']]];
                }
            }
            $contenidos[] = ['role' => $turno['rol'], 'parts' => $partes];
        }

        $generacion = [
            'temperature' => $p->temperatura,
            'maxOutputTokens' => $p->maxTokens ?? (int) config('sgsi.ia.limites.max_tokens_respuesta'),
        ];
        if ($p->json) {
            $generacion['responseMimeType'] = 'application/json';
        }
        // thinkingLevel solo existe en la familia 3.x; a otros modelos les daría 400.
        $nivel = config('sgsi.ia.gemini.thinking_level');
        if (filled($nivel) && str_starts_with($modelo, 'gemini-3')) {
            $generacion['thinkingConfig'] = ['thinkingLevel' => $nivel];
        }

        $cuerpo = ['contents' => $contenidos, 'generationConfig' => $generacion];
        if (filled($p->instruccionSistema)) {
            $cuerpo['systemInstruction'] = ['parts' => [['text' => $p->instruccionSistema]]];
        }

        return $cuerpo;
    }

    /** Un 503 de Gemini suele ser «alta demanda» pasajera: se reintenta una vez el mismo modelo. */
    private function llamar(string $modelo, array $cuerpo, int $timeout): Response
    {
        $respuesta = $this->cliente($timeout)->post(self::BASE . "{$modelo}:generateContent", $cuerpo);
        if ($respuesta->status() === 503) {
            usleep(self::ESPERA_REINTENTO_MS * 1000);
            $respuesta = $this->cliente($timeout)->post(self::BASE . "{$modelo}:generateContent", $cuerpo);
        }

        return $respuesta;
    }

    private function cliente(int $timeout)
    {
        // La clave va en cabecera (no en ?key=) para que no quede en logs de URL.
        return Http::withHeaders(['x-goog-api-key' => (string) config('sgsi.ia.gemini.key')])
            ->acceptJson()
            ->connectTimeout(min(10, $timeout))
            ->timeout($timeout);
    }

    private function textoDe(Response $respuesta): string
    {
        $partes = (array) $respuesta->json('candidates.0.content.parts', []);
        $texto = '';
        foreach ($partes as $parte) {
            // Las partes de razonamiento interno (thought=true) no se muestran.
            if (is_array($parte) && empty($parte['thought']) && is_string($parte['text'] ?? null)) {
                $texto .= $parte['text'];
            }
        }

        return trim($texto);
    }

    private function codigoError(Response $respuesta): string
    {
        $estado = (string) $respuesta->json('error.status', '');
        $mensaje = (string) $respuesta->json('error.message', '');

        return match (true) {
            $respuesta->status() === 400 && (str_contains($mensaje, 'API key') || str_contains($estado, 'INVALID_ARGUMENT') && str_contains(strtolower($mensaje), 'key')) => 'API_KEY_INVALIDA',
            in_array($respuesta->status(), [401, 403], true) => 'API_KEY_INVALIDA',
            $respuesta->status() === 404 => 'MODELO_NO_DISPONIBLE',
            $respuesta->status() === 429 => 'CUOTA_EXCEDIDA',
            $respuesta->status() === 503 => 'SATURADO',
            $respuesta->status() === 400 => 'PETICION_INVALIDA',
            default => 'ERROR_PROVEEDOR',
        };
    }

    /** @param list<float> $v */
    private function normalizar(array $v): array
    {
        $norma = sqrt(array_sum(array_map(fn ($x) => $x * $x, $v)));

        return $norma > 0 ? array_map(fn ($x) => $x / $norma, $v) : $v;
    }

    private function ms(int|float $inicio): int
    {
        return (int) round((hrtime(true) - $inicio) / 1_000_000);
    }
}
