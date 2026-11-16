<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\ClausulaIso;
use App\Models\ControlIso;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Validación de lo que devuelve el modelo ANTES de mostrarlo (OWASP LLM05
 * Improper Output Handling): tipos, rangos, listas acotadas, valores de
 * catálogo, y referencias normativas verificadas contra la base de datos
 * (un código de control o cláusula inexistente se descarta: no se inventan
 * referencias normativas).
 */
final class AIResultValidator
{
    /**
     * Numeración (solo números, sin texto normativo) de las subcláusulas de
     * ISO/IEC 27001:2022, para descartar referencias inventadas como «6.9».
     */
    private const SUBCLAUSULAS_VALIDAS = [
        '4.1', '4.2', '4.3', '4.4', '5.1', '5.2', '5.3', '6.1', '6.1.1', '6.1.2', '6.1.3', '6.2', '6.3',
        '7.1', '7.2', '7.3', '7.4', '7.5', '7.5.1', '7.5.2', '7.5.3', '8.1', '8.2', '8.3',
        '9.1', '9.2', '9.2.1', '9.2.2', '9.3', '9.3.1', '9.3.2', '9.3.3', '10.1', '10.2',
    ];

    private ?Collection $controles = null;
    private ?Collection $clausulas = null;

    public function texto(mixed $valor, int $max = 600): string
    {
        return is_scalar($valor) ? Str::limit(trim(strip_tags((string) $valor)), $max, '…') : '';
    }

    /** @return list<string> */
    public function lista(mixed $valor, int $maxElementos = 6, int $maxLargo = 300): array
    {
        if (!is_array($valor)) {
            return [];
        }

        return collect($valor)
            ->map(fn ($v) => is_array($v) ? ($v['descripcion'] ?? $v['texto'] ?? null) : $v)
            ->filter(fn ($v) => is_scalar($v) && trim((string) $v) !== '')
            ->map(fn ($v) => $this->texto($v, $maxLargo))
            ->unique()->take($maxElementos)->values()->all();
    }

    public function escala(mixed $valor, int $min = 1, int $max = 5): ?int
    {
        return is_numeric($valor) ? max($min, min($max, (int) round((float) $valor))) : null;
    }

    /** @param list<string> $permitidos */
    public function opcion(mixed $valor, array $permitidos, ?string $porDefecto = null): ?string
    {
        $v = is_string($valor) ? Str::lower(trim(Str::ascii($valor))) : null;
        $v = $v !== null ? str_replace([' ', '-'], '_', $v) : null;

        return in_array($v, $permitidos, true) ? $v : $porDefecto;
    }

    /**
     * Conserva solo códigos del Anexo A que existen en `controles_iso`, con el
     * nombre oficial de la BD (lógica original de IaRiesgoService).
     *
     * @return array{0: list<array{id: int, codigo: string, nombre: string, justificacion: string}>, 1: int}
     */
    public function controles(mixed $sugeridos, int $max = 5): array
    {
        $sugeridos = is_array($sugeridos) ? $sugeridos : [];
        $catalogo = $this->catalogoControles();

        $validos = collect($sugeridos)
            ->filter(fn ($c) => is_array($c) && is_string($c['codigo'] ?? null))
            ->map(function (array $c) use ($catalogo) {
                $codigo = Str::start(strtoupper(trim($c['codigo'])), 'A.');
                $control = $catalogo->get($codigo);

                return $control === null ? null : [
                    'id' => $control->id,
                    'codigo' => $control->codigo,
                    'nombre' => $control->nombre,
                    'estado_implementacion' => $control->estado_implementacion?->value,
                    'justificacion' => $this->texto($c['justificacion'] ?? '', 600),
                ];
            })
            ->filter()->unique('codigo')->take($max)->values()->all();

        return [$validos, max(0, count($sugeridos) - count($validos))];
    }

    /**
     * Referencia normativa verificada: control del Anexo A (A.x.y) o cláusula
     * de ISO/IEC 27001 (4 a 10.x) registrada en el catálogo. Si no existe → null.
     */
    public function referencia(mixed $valor): ?string
    {
        if (!is_string($valor) || trim($valor) === '') {
            return null;
        }
        if (preg_match('/\bA\.(\d{1,2})\.(\d{1,2})\b/i', $valor, $m)) {
            $control = $this->catalogoControles()->get("A.{$m[1]}.{$m[2]}");

            return $control ? "{$control->codigo} {$control->nombre}" : null;
        }
        if (preg_match('/\b(?:cl[aá]usula\s*)?(\d{1,2}(?:\.\d{1,2}){0,2})\b/iu', $valor, $m) && (int) $m[1] >= 4 && (int) $m[1] <= 10) {
            $numero = $m[1];
            if (str_contains($numero, '.') && !in_array($numero, self::SUBCLAUSULAS_VALIDAS, true)) {
                return null;
            }
            $clausula = $this->catalogoClausulas()->first(fn ($c) => $c->numero === $numero)
                ?? $this->catalogoClausulas()->first(fn ($c) => $c->numero === explode('.', $numero)[0]);

            return $clausula ? "Cláusula {$numero} ({$clausula->nombre})" : null;
        }

        return null;
    }

    private function catalogoControles(): Collection
    {
        return $this->controles ??= ControlIso::query()->get()->keyBy('codigo');
    }

    private function catalogoClausulas(): Collection
    {
        return $this->clausulas ??= ClausulaIso::query()->get();
    }
}
