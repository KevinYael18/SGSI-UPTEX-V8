<?php

declare(strict_types=1);

namespace App\Services\AI;

final class GeminiRespuesta
{
    public function __construct(
        public readonly string $texto,
        public readonly string $modelo,
        public readonly int $tokensEntrada,
        public readonly int $tokensSalida,
        public readonly int $latenciaMs,
        public readonly ?string $finishReason = null,
    ) {
    }

    /** Decodifica la respuesta como JSON (tolera el envoltorio ```json ... ```). */
    public function json(): ?array
    {
        $limpio = trim(str_replace(['```json', '```JSON', '```'], '', trim($this->texto)));
        $datos = json_decode($limpio, true);

        return is_array($datos) ? $datos : null;
    }
}
