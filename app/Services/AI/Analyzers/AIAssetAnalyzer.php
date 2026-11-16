<?php

declare(strict_types=1);

namespace App\Services\AI\Analyzers;

use App\Models\Activo;
use App\Models\Amenaza;
use App\Models\Departamento;
use App\Models\Usuario;
use App\Models\Vulnerabilidad;
use App\Services\AI\AIResultValidator;
use App\Services\AI\GeminiRespuesta;
use App\Services\AI\SGSIIntelligenceService;
use App\Support\CriticidadActivoCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Análisis de activos: clasificación CID sugerida, riesgos asociados (contra
 * el catálogo de amenazas/vulnerabilidades del tipo de activo), medidas de
 * protección, controles del Anexo A y mejoras del inventario.
 *
 * Funciona sobre un activo existente o sobre el BORRADOR del formulario de
 * alta (antes de guardar). Nunca crea ni modifica el activo: el usuario
 * decide qué aplica al formulario.
 */
final class AIAssetAnalyzer
{
    public const TIPOS = ['hardware', 'software', 'informacion', 'servicio', 'infraestructura'];
    public const NIVELES_INFORMACION = ['publica', 'interna', 'confidencial', 'restringida'];

    public function __construct(
        private readonly SGSIIntelligenceService $asistente,
        private readonly AIResultValidator $validar,
        private readonly CriticidadActivoCalculator $criticidad,
    ) {
    }

    /** @param array<string, mixed> $borrador campos del formulario (tipo, nombre, subtipo, caracteristicas, C/I/D, departamento_id…) */
    public function analizar(Usuario $usuario, ?Activo $activo, array $borrador = []): array
    {
        $campos = $activo !== null ? $this->desdeActivo($activo) : $this->desdeBorrador($borrador);
        $faltantes = $this->camposFaltantes($campos);

        $catalogo = $this->catalogo($campos['tipo']);
        $datos = "Activo" . ($activo ? ' registrado' : ' en borrador (aún no guardado)') . ":\n"
            . "- Nombre: " . ($campos['nombre'] ?: 'sin nombre') . "\n"
            . "- Tipo: " . ($campos['tipo'] ?: 'sin definir') . ($campos['subtipo'] ? " / subtipo {$campos['subtipo']}" : '') . "\n"
            . "- Valoración actual (1-5): confidencialidad " . ($campos['confidencialidad'] ?? 'sin dato')
            . ', integridad ' . ($campos['integridad'] ?? 'sin dato') . ', disponibilidad ' . ($campos['disponibilidad'] ?? 'sin dato') . "\n"
            . "- Departamento: " . ($campos['departamento'] ?? 'sin asignar') . "\n"
            . "- Responsable asignado: " . ($campos['tiene_responsable'] ? 'sí' : 'no') . "\n"
            . "- Ubicación registrada: " . ($campos['tiene_ubicacion'] ? 'sí' : 'no') . "\n"
            . "- Características técnicas: " . ($campos['caracteristicas'] !== '' ? $campos['caracteristicas'] : 'ninguna') . "\n"
            . "- Controles del Anexo A vinculados: " . ($campos['controles'] !== '' ? $campos['controles'] : 'ninguno') . "\n\n"
            . "Catálogo de amenazas del sistema para este tipo: {$catalogo['amenazas']}\n"
            . "Catálogo de vulnerabilidades del sistema para este tipo: {$catalogo['vulnerabilidades']}";

        $tarea = 'Analiza este activo de información de una universidad según ISO/IEC 27001:2022 (A.5.9 inventario, A.5.12 clasificación). '
            . 'Sugiere su valoración de confidencialidad, integridad y disponibilidad en la escala del sistema (enteros 1-5) y su nivel de clasificación '
            . '(publica, interna, confidencial o restringida). Para los riesgos asociados usa preferentemente amenazas y vulnerabilidades del catálogo del sistema '
            . '(escribe su nombre exacto). Indica medidas de protección concretas, hasta 5 controles reales del Anexo A, vulnerabilidades posibles '
            . 'derivadas SOLO de la información proporcionada y mejoras para el registro de inventario. No inventes datos del activo que no se proporcionaron.';

        $estructura = '{"resumen": "...", "clasificacion_sugerida": {"confidencialidad": 1, "integridad": 1, "disponibilidad": 1, "justificacion": "..."}, '
            . '"nivel_informacion": "interna", "riesgos_asociados": [{"amenaza": "...", "vulnerabilidad": "...", "descripcion": "..."}], '
            . '"vulnerabilidades_posibles": ["..."], "medidas_proteccion": ["..."], '
            . '"controles_sugeridos": [{"codigo": "A.X.X", "nombre": "...", "justificacion": "..."}], "mejoras_inventario": ["..."]}';

        $analisis = $this->asistente->analizar(
            $usuario, 'activo', $activo, $tarea, $datos, $estructura,
            fn (array $d, GeminiRespuesta $r) => $this->normalizar($d, $r, $faltantes, $catalogo['nombres']),
            ['activo_id' => $activo?->id, 'borrador' => $activo === null],
        );

        return ['analisis_uuid' => $analisis->uuid] + $analisis->resultado;
    }

    private function desdeActivo(Activo $activo): array
    {
        $activo->loadMissing('departamento:id,nombre', 'controlesIso');

        return [
            'nombre' => $activo->nombre,
            'tipo' => $activo->tipo?->value,
            'subtipo' => $activo->subtipo,
            'confidencialidad' => $activo->confidencialidad,
            'integridad' => $activo->integridad,
            'disponibilidad' => $activo->disponibilidad,
            'departamento' => $activo->departamento?->nombre,
            'tiene_departamento' => $activo->departamento_id !== null,
            'tiene_responsable' => $activo->responsable_id !== null,
            'tiene_ubicacion' => $activo->ubicacion_id !== null,
            'caracteristicas' => $this->caracteristicas($activo->caracteristicas),
            'controles' => $activo->controlesIso->map(fn ($c) => "{$c->codigo} ({$c->estado_implementacion?->value})")->implode(', '),
        ];
    }

    private function desdeBorrador(array $b): array
    {
        $departamento = isset($b['departamento_id']) ? Departamento::query()->find($b['departamento_id']) : null;

        return [
            'nombre' => mb_substr((string) ($b['nombre'] ?? ''), 0, 150),
            'tipo' => in_array($b['tipo'] ?? null, self::TIPOS, true) ? $b['tipo'] : null,
            'subtipo' => isset($b['subtipo']) ? mb_substr((string) $b['subtipo'], 0, 40) : null,
            'confidencialidad' => $this->validar->escala($b['confidencialidad'] ?? null),
            'integridad' => $this->validar->escala($b['integridad'] ?? null),
            'disponibilidad' => $this->validar->escala($b['disponibilidad'] ?? null),
            'departamento' => $departamento?->nombre,
            'tiene_departamento' => $departamento !== null,
            'tiene_responsable' => filled($b['responsable_id'] ?? null),
            'tiene_ubicacion' => filled($b['ubicacion_id'] ?? null),
            'caracteristicas' => $this->caracteristicas($b['caracteristicas'] ?? null),
            'controles' => '',
        ];
    }

    /** Características técnicas en texto acotado (sin números de serie ni datos de contacto). */
    private function caracteristicas(mixed $valor): string
    {
        if (!is_array($valor)) {
            return '';
        }
        $omitir = ['numero_serie', 'serie', 'serial', 'mac', 'direccion_mac', 'ip', 'direccion_ip', 'usuario', 'contrasena', 'password', 'correo', 'telefono'];

        // Ficha técnica: {campos: {clave: valor}, personalizados: [{nombre, valor}]} (o plana).
        $plano = is_array($valor['campos'] ?? null) ? $valor['campos'] : array_filter($valor, 'is_scalar');
        foreach ((array) ($valor['personalizados'] ?? []) as $p) {
            if (is_array($p) && filled($p['nombre'] ?? null)) {
                $plano[(string) $p['nombre']] = $p['valor'] ?? '';
            }
        }

        return collect($plano)
            ->reject(fn ($v, $k) => in_array(strtolower((string) $k), $omitir, true) || !is_scalar($v) || trim((string) $v) === '')
            ->map(fn ($v, $k) => str_replace('_', ' ', (string) $k) . ': ' . mb_substr((string) $v, 0, 80))
            ->take(25)->implode('; ');
    }

    /** Verificación DETERMINISTA (no IA) de los campos que faltan en el registro. */
    private function camposFaltantes(array $c): array
    {
        $faltantes = [];
        foreach (['nombre' => 'Nombre', 'tipo' => 'Clasificación (tipo de activo)'] as $campo => $etiqueta) {
            if (blank($c[$campo])) {
                $faltantes[] = $etiqueta;
            }
        }
        foreach (['confidencialidad' => 'Confidencialidad', 'integridad' => 'Integridad', 'disponibilidad' => 'Disponibilidad'] as $campo => $etiqueta) {
            if ($c[$campo] === null) {
                $faltantes[] = "Valoración de {$etiqueta}";
            }
        }
        if (!$c['tiene_departamento']) {
            $faltantes[] = 'Departamento';
        }
        if (!$c['tiene_responsable']) {
            $faltantes[] = 'Responsable (propietario del activo, ISO 27001 A.5.9)';
        }
        if (!$c['tiene_ubicacion']) {
            $faltantes[] = 'Ubicación';
        }
        if ($c['caracteristicas'] === '' && in_array($c['tipo'], ['hardware', 'software', 'infraestructura'], true)) {
            $faltantes[] = 'Ficha técnica (características)';
        }

        return $faltantes;
    }

    /** @return array{amenazas: string, vulnerabilidades: string, nombres: array<string, array<string, int>>} */
    private function catalogo(?string $tipo): array
    {
        $amenazas = Amenaza::query()
            ->when($tipo && Schema::hasTable('amenaza_tipo_activo'), fn ($q) => $q->whereIn('id', DB::table('amenaza_tipo_activo')->where('tipo_activo', $tipo)->pluck('amenaza_id')))
            ->get(['id', 'nombre']);
        $vulnerabilidades = Vulnerabilidad::query()
            ->when($tipo && Schema::hasTable('vulnerabilidad_tipo_activo'), fn ($q) => $q->whereIn('id', DB::table('vulnerabilidad_tipo_activo')->where('tipo_activo', $tipo)->pluck('vulnerabilidad_id')))
            ->get(['id', 'nombre']);

        // Si el tipo no tiene catálogo asociado (o la relación por tipo no existe
        // en esta instalación), se ofrece el catálogo completo.
        if ($amenazas->isEmpty()) {
            $amenazas = Amenaza::query()->get(['id', 'nombre']);
        }
        if ($vulnerabilidades->isEmpty()) {
            $vulnerabilidades = Vulnerabilidad::query()->get(['id', 'nombre']);
        }

        return [
            'amenazas' => $amenazas->pluck('nombre')->implode('; '),
            'vulnerabilidades' => $vulnerabilidades->pluck('nombre')->implode('; '),
            'nombres' => [
                'amenazas' => $amenazas->mapWithKeys(fn ($a) => [mb_strtolower($a->nombre) => $a->id])->all(),
                'vulnerabilidades' => $vulnerabilidades->mapWithKeys(fn ($v) => [mb_strtolower($v->nombre) => $v->id])->all(),
            ],
        ];
    }

    private function normalizar(array $d, GeminiRespuesta $r, array $faltantes, array $nombres): array
    {
        $cid = is_array($d['clasificacion_sugerida'] ?? null) ? $d['clasificacion_sugerida'] : [];
        $c = $this->validar->escala($cid['confidencialidad'] ?? null);
        $i = $this->validar->escala($cid['integridad'] ?? null);
        $di = $this->validar->escala($cid['disponibilidad'] ?? null);

        [$controles, $descartados] = $this->validar->controles($d['controles_sugeridos'] ?? []);

        $riesgos = collect(is_array($d['riesgos_asociados'] ?? null) ? $d['riesgos_asociados'] : [])
            ->filter(fn ($x) => is_array($x) && filled($x['amenaza'] ?? null))
            ->take(6)
            ->map(function (array $x) use ($nombres) {
                $amenaza = $this->validar->texto($x['amenaza'], 150);
                $vulnerabilidad = $this->validar->texto($x['vulnerabilidad'] ?? '', 150);

                return [
                    'amenaza' => $amenaza,
                    'amenaza_id' => $nombres['amenazas'][mb_strtolower($amenaza)] ?? null,
                    'vulnerabilidad' => $vulnerabilidad,
                    'vulnerabilidad_id' => $nombres['vulnerabilidades'][mb_strtolower($vulnerabilidad)] ?? null,
                    'descripcion' => $this->validar->texto($x['descripcion'] ?? '', 300),
                ];
            })->values()->all();

        return [
            'resumen' => $this->validar->texto($d['resumen'] ?? '', 700),
            'clasificacion_sugerida' => ($c && $i && $di) ? [
                'confidencialidad' => $c,
                'integridad' => $i,
                'disponibilidad' => $di,
                // Misma fórmula del sistema (máximo de C, I, D), no la decide la IA.
                'criticidad_resultante' => $this->criticidad->calcular($c, $i, $di),
                'justificacion' => $this->validar->texto($cid['justificacion'] ?? ''),
            ] : null,
            'nivel_informacion' => $this->validar->opcion($d['nivel_informacion'] ?? null, self::NIVELES_INFORMACION),
            'riesgos_asociados' => $riesgos,
            'vulnerabilidades_posibles' => $this->validar->lista($d['vulnerabilidades_posibles'] ?? []),
            'medidas_proteccion' => $this->validar->lista($d['medidas_proteccion'] ?? [], 8),
            'controles_sugeridos' => $controles,
            'controles_descartados' => $descartados,
            'mejoras_inventario' => $this->validar->lista($d['mejoras_inventario'] ?? []),
            'campos_faltantes' => $faltantes,
            'modelo' => $r->modelo,
        ];
    }
}
