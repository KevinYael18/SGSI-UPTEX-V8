<?php

declare(strict_types=1);

namespace App\Services\AI;

final class ClasificacionSolicitud
{
    /**
     * @param list<string> $modulos módulos de GSI UPTex a los que alude la solicitud
     *                              (activos, riesgos, incidentes, auditorias, documentos, cumplimiento, dashboard)
     */
    public function __construct(
        public readonly string $categoria,
        public readonly bool $permitido,
        public readonly string $origen,   // reglas | llm | saludo | guardia
        public readonly string $motivo = '',
        public readonly array $modulos = [],
    ) {
    }

    public function esSaludo(): bool
    {
        return $this->origen === 'saludo';
    }

    public function toArray(): array
    {
        return [
            'categoria' => $this->categoria,
            'permitido' => $this->permitido,
            'origen' => $this->origen,
            'modulos' => $this->modulos,
        ];
    }
}
