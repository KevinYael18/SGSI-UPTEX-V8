<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Petición de generación para GeminiService. Inmutable: cada módulo describe
 * QUÉ quiere (instrucción de sistema, turnos, formato) y GeminiService decide
 * CÓMO hablar con el proveedor (modelo, respaldo, reintentos, consumo).
 */
final class GeminiPeticion
{
    /**
     * @param list<array{rol: 'user'|'model', texto: string}> $turnos
     * @param list<array{mime: string, datos: string}> $adjuntos  archivos en base64 (p. ej. PDF a extraer)
     */
    public function __construct(
        public readonly string $operacion,
        public readonly array $turnos,
        public readonly ?string $instruccionSistema = null,
        public readonly bool $json = false,
        public readonly float $temperatura = 0.2,
        public readonly ?int $maxTokens = null,
        public readonly ?int $usuarioId = null,
        public readonly ?string $modelo = null,       // fuerza un modelo concreto (sin respaldo)
        public readonly ?string $categoria = null,
        public readonly array $adjuntos = [],
    ) {
    }

    public static function simple(string $operacion, string $texto, ?string $sistema = null, bool $json = true, ?int $usuarioId = null): self
    {
        return new self($operacion, [['rol' => 'user', 'texto' => $texto]], $sistema, $json, usuarioId: $usuarioId);
    }
}
