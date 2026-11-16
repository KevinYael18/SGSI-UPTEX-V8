<?php

declare(strict_types=1);

namespace App\Services\AI\Analyzers;

use App\Models\Activo;
use App\Models\AccionCorrectiva;
use App\Models\ControlIso;
use App\Models\Incidente;
use App\Models\NoConformidad;
use App\Models\Riesgo;
use App\Models\Usuario;
use App\Services\AI\AIAccessControl;
use App\Services\AI\AIContextBuilder;
use App\Services\AI\AIResultValidator;
use App\Services\AI\GeminiRespuesta;
use App\Services\AI\SGSIIntelligenceService;

/**
 * Resumen ejecutivo del SGSI para el Dashboard. Los INDICADORES se calculan
 * en Laravel sobre registros reales y se devuelven tal cual (la IA no los
 * produce ni los modifica); la IA solo redacta el resumen y las prioridades a
 * partir de esos datos. Respeta los permisos: solo entran los módulos que el
 * usuario puede ver.
 */
final class AIDashboardAnalyzer
{
    public const ESTADOS = ['adecuado', 'requiere_atencion', 'critico', 'sin_datos_suficientes'];
    public const PRIORIDADES = ['alta', 'media', 'baja'];

    public function __construct(
        private readonly SGSIIntelligenceService $asistente,
        private readonly AIResultValidator $validar,
        private readonly AIContextBuilder $contexto,
        private readonly AIAccessControl $acceso,
    ) {
    }

    public function analizar(Usuario $usuario): array
    {
        $this->acceso->exigir($usuario, 'dashboard.ver', 'ver el resumen ejecutivo del SGSI');

        $modulos = array_values(array_diff($this->acceso->modulosPermitidos($usuario), ['dashboard']));
        $indicadores = $this->indicadores($usuario, $modulos);

        $bloques = [];
        foreach ($modulos as $modulo) {
            $bloques[] = match ($modulo) {
                'activos' => $this->contexto->activos($usuario),
                'riesgos' => $this->contexto->riesgos($usuario),
                'incidentes' => $this->contexto->incidentes($usuario),
                'auditorias' => $this->contexto->auditorias(),
                'documentos' => $this->contexto->documentos(),
                'cumplimiento' => $this->contexto->cumplimiento(),
                default => '',
            };
        }
        $datos = "Indicadores calculados por el sistema:\n" . json_encode($indicadores, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\n" . implode("\n\n", array_filter($bloques))
            . "\n\nMódulos visibles para este usuario: " . implode(', ', $modulos) . '.';

        $tarea = 'Elabora un resumen ejecutivo del estado del SGSI para la dirección, usando EXCLUSIVAMENTE los indicadores y datos proporcionados '
            . '(no inventes cifras ni registros; si mencionas un número debe aparecer en los datos). Determina un estado general, '
            . 'las prioridades (máximo 6, cada una asociada a uno de los módulos visibles) y las áreas de mejora. '
            . 'Si los datos son insuficientes para alguna conclusión, indícalo en "datos_insuficientes".';

        $estructura = '{"resumen_ejecutivo": "...", "estado_general": "requiere_atencion", '
            . '"prioridades": [{"titulo": "...", "descripcion": "...", "prioridad": "alta", "modulo": "riesgos"}], '
            . '"areas_mejora": ["..."], "datos_insuficientes": ["..."]}';

        $analisis = $this->asistente->analizar(
            $usuario, 'dashboard', null, $tarea, $datos, $estructura,
            fn (array $d, GeminiRespuesta $r) => $this->normalizar($d, $r, $indicadores, $modulos),
            ['modulos' => $modulos],
        );

        return ['analisis_uuid' => $analisis->uuid] + $analisis->resultado;
    }

    /** Cifras reales (solo de los módulos permitidos). */
    private function indicadores(Usuario $usuario, array $modulos): array
    {
        $i = [];
        if (in_array('activos', $modulos, true)) {
            $base = $this->acceso->acotarActivos($usuario, Activo::query());
            $i['activos_totales'] = (clone $base)->count();
            $i['activos_criticidad_alta'] = (clone $base)->where('criticidad_calculada', '>=', 4)->count();
            $i['activos_sin_responsable'] = (clone $base)->whereNull('responsable_id')->count();
        }
        if (in_array('riesgos', $modulos, true)) {
            $i['riesgos_totales'] = Riesgo::query()->count();
            $i['riesgos_criticos'] = Riesgo::query()->where('nivel_calculado', 'critico')->count();
            $i['riesgos_altos'] = Riesgo::query()->where('nivel_calculado', 'alto')->count();
            $i['riesgos_sin_tratamiento'] = Riesgo::query()->whereNull('tipo_tratamiento')->count();
        }
        if (in_array('incidentes', $modulos, true)) {
            $i['incidentes_sin_resolver'] = Incidente::query()->whereIn('estado', ['abierto', 'en_analisis', 'contenido'])->count();
        }
        if (in_array('auditorias', $modulos, true)) {
            $i['no_conformidades_abiertas'] = NoConformidad::query()->whereIn('estado', ['abierta', 'en_proceso', 'vencida'])->count();
            $i['acciones_correctivas_vencidas'] = AccionCorrectiva::query()->where('estado', '!=', 'cerrada')->whereDate('fecha_compromiso', '<', now())->count();
        }
        if (in_array('cumplimiento', $modulos, true)) {
            $aplicables = ControlIso::query()->where('estado_implementacion', '!=', 'no_aplica')->count();
            $implementados = ControlIso::query()->where('estado_implementacion', 'implementado')->count();
            $i['controles_implementados'] = $implementados;
            $i['controles_aplicables'] = $aplicables;
            $i['cumplimiento_anexo_a_pct'] = $aplicables > 0 ? round($implementados * 100 / $aplicables, 1) : 0;
        }

        return $i;
    }

    private function normalizar(array $d, GeminiRespuesta $r, array $indicadores, array $modulos): array
    {
        $prioridades = collect(is_array($d['prioridades'] ?? null) ? $d['prioridades'] : [])
            ->filter(fn ($p) => is_array($p) && filled($p['titulo'] ?? null))
            ->take(6)
            ->map(fn (array $p) => [
                'titulo' => $this->validar->texto($p['titulo'], 120),
                'descripcion' => $this->validar->texto($p['descripcion'] ?? '', 400),
                'prioridad' => $this->validar->opcion($p['prioridad'] ?? null, self::PRIORIDADES, 'media'),
                // Solo módulos que el usuario puede ver (para enlazar desde la UI).
                'modulo' => $this->validar->opcion($p['modulo'] ?? null, $modulos),
            ])->values()->all();

        return [
            'resumen_ejecutivo' => $this->validar->texto($d['resumen_ejecutivo'] ?? '', 1500),
            'estado_general' => $indicadores === [] ? 'sin_datos_suficientes' : $this->validar->opcion($d['estado_general'] ?? null, self::ESTADOS, 'requiere_atencion'),
            'prioridades' => $prioridades,
            'areas_mejora' => $this->validar->lista($d['areas_mejora'] ?? []),
            'datos_insuficientes' => $this->validar->lista($d['datos_insuficientes'] ?? []),
            'indicadores' => $indicadores,
            'generado_en' => now()->toIso8601String(),
            'modelo' => $r->modelo,
        ];
    }
}
