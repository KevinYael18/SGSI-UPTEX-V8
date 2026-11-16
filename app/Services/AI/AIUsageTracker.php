<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Exceptions\IaLimiteConsumoException;
use App\Models\IaRegistroUso;
use Illuminate\Support\Facades\Log;

/**
 * Control de consumo del asistente: registra cada llamada al proveedor (y
 * cada rechazo local, con 0 tokens) y aplica los presupuestos diarios de
 * tokens por usuario y global ANTES de llamar a Gemini.
 */
final class AIUsageTracker
{
    public function verificarPresupuesto(?int $usuarioId): void
    {
        $limites = config('sgsi.ia.limites');

        if ($this->tokensHoy(null) >= (int) $limites['tokens_diarios_global']) {
            Log::warning('IA: se alcanzó el presupuesto diario GLOBAL de tokens.');
            throw new IaLimiteConsumoException('SGSI Intelligence Assistant alcanzó su límite diario de uso institucional. Intente de nuevo mañana.');
        }

        if ($usuarioId !== null && $this->tokensHoy($usuarioId) >= (int) $limites['tokens_diarios_usuario']) {
            throw new IaLimiteConsumoException();
        }
    }

    public function registrar(
        string $operacion,
        ?int $usuarioId,
        ?string $modelo,
        int $tokensEntrada = 0,
        int $tokensSalida = 0,
        bool $exito = true,
        ?string $codigoError = null,
        ?int $latenciaMs = null,
        ?string $categoria = null,
    ): void {
        try {
            IaRegistroUso::query()->create([
                'usuario_id' => $usuarioId,
                'operacion' => substr($operacion, 0, 30),
                'proveedor' => 'gemini',
                'modelo' => $modelo,
                'categoria' => $categoria,
                'tokens_entrada' => max(0, $tokensEntrada),
                'tokens_salida' => max(0, $tokensSalida),
                'exito' => $exito,
                'codigo_error' => $codigoError,
                'latencia_ms' => $latenciaMs,
            ]);
        } catch (\Throwable $e) {
            // El registro de consumo nunca debe tumbar la respuesta al usuario.
            Log::warning('IA: no se pudo registrar el consumo', ['error' => $e->getMessage()]);
        }
    }

    public function tokensHoy(?int $usuarioId): int
    {
        $consulta = IaRegistroUso::query()->where('created_at', '>=', now()->startOfDay());
        if ($usuarioId !== null) {
            $consulta->where('usuario_id', $usuarioId);
        }

        return (int) $consulta->sum('tokens_entrada') + (int) $consulta->sum('tokens_salida');
    }

    /** Resumen para el panel administrativo (sin contenido de mensajes). */
    public function resumen(int $dias = 7): array
    {
        $desde = now()->subDays($dias - 1)->startOfDay();

        $porDia = IaRegistroUso::query()
            ->where('created_at', '>=', $desde)
            ->selectRaw('DATE(created_at) as dia, COUNT(*) as solicitudes, SUM(tokens_entrada + tokens_salida) as tokens, SUM(CASE WHEN exito = 0 THEN 1 ELSE 0 END) as errores')
            ->groupBy('dia')->orderBy('dia')->get()
            ->map(fn ($f) => ['dia' => (string) $f->dia, 'solicitudes' => (int) $f->solicitudes, 'tokens' => (int) $f->tokens, 'errores' => (int) $f->errores]);

        $porOperacion = IaRegistroUso::query()
            ->where('created_at', '>=', $desde)
            ->selectRaw('operacion, COUNT(*) as solicitudes, SUM(tokens_entrada + tokens_salida) as tokens')
            ->groupBy('operacion')->orderByDesc('solicitudes')->get()
            ->map(fn ($f) => ['operacion' => $f->operacion, 'solicitudes' => (int) $f->solicitudes, 'tokens' => (int) $f->tokens]);

        $rechazos = IaRegistroUso::query()
            ->where('created_at', '>=', $desde)
            ->whereIn('codigo_error', ['FUERA_DE_DOMINIO', 'INTENTO_MANIPULACION', 'ACCION_NO_PERMITIDA'])
            ->selectRaw('codigo_error, COUNT(*) as total')->groupBy('codigo_error')->pluck('total', 'codigo_error');

        return [
            'dias' => $dias,
            'tokens_hoy' => $this->tokensHoy(null),
            'limite_global' => (int) config('sgsi.ia.limites.tokens_diarios_global'),
            'limite_usuario' => (int) config('sgsi.ia.limites.tokens_diarios_usuario'),
            'por_dia' => $porDia,
            'por_operacion' => $porOperacion,
            'rechazos' => $rechazos,
        ];
    }
}
