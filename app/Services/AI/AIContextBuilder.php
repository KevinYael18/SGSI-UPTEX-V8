<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AccionCorrectiva;
use App\Models\Activo;
use App\Models\Auditoria;
use App\Models\ControlIso;
use App\Models\Documento;
use App\Models\Hallazgo;
use App\Models\Incidente;
use App\Models\NoConformidad;
use App\Models\Riesgo;
use App\Models\Usuario;
use Illuminate\Support\Str;

/**
 * «Herramientas» controladas de consulta del asistente. Laravel —no el
 * modelo— decide qué datos se recuperan, siempre con los permisos del
 * usuario (AIAccessControl). El modelo recibe un resumen de solo lectura:
 *
 *  - Sin datos personales innecesarios: no se envían correos, teléfonos ni
 *    nombres de personas; solo nombres de activos, estados y conteos.
 *  - Sin módulos no autorizados: se le indica explícitamente al modelo que no
 *    tiene acceso, para que no invente datos de ese módulo.
 *  - Tamaño acotado (top-N por módulo).
 */
final class AIContextBuilder
{
    private const TOP = 8;

    /** Categoría del clasificador → módulo cuyo contexto ayuda a responder. */
    private const MODULO_POR_CATEGORIA = [
        'GESTION_ACTIVOS' => 'activos',
        'GESTION_RIESGOS' => 'riesgos',
        'GESTION_INCIDENTES' => 'incidentes',
        'AUDITORIAS' => 'auditorias',
        'DOCUMENTACION' => 'documentos',
        'ISO_27001' => 'cumplimiento',
    ];

    public function __construct(private readonly AIAccessControl $acceso)
    {
    }

    /**
     * @return array{texto: string, consultados: list<string>, denegados: list<string>}
     */
    public function paraConsulta(Usuario $usuario, ClasificacionSolicitud $clasificacion, string $mensaje): array
    {
        $modulos = $clasificacion->modulos;
        if (isset(self::MODULO_POR_CATEGORIA[$clasificacion->categoria])) {
            $modulos[] = self::MODULO_POR_CATEGORIA[$clasificacion->categoria];
        }
        // Un resumen general consulta todos los módulos que el usuario puede ver.
        if (in_array('dashboard', $modulos, true)) {
            $modulos = [...$modulos, 'activos', 'riesgos', 'incidentes', 'auditorias', 'documentos', 'cumplimiento'];
        }
        $modulos = array_values(array_unique($modulos));

        $bloques = [];
        $consultados = [];
        $denegados = [];
        foreach ($modulos as $modulo) {
            if (!$this->acceso->puedeModulo($usuario, $modulo)) {
                $denegados[] = $modulo;
                continue;
            }
            $bloque = match ($modulo) {
                'activos' => $this->activos($usuario),
                'riesgos' => $this->riesgos($usuario),
                'incidentes' => $this->incidentes($usuario),
                'auditorias' => $this->auditorias(),
                'documentos' => $this->documentos(),
                'cumplimiento' => $this->cumplimiento($mensaje),
                default => null,
            };
            if ($bloque !== null) {
                $bloques[] = $bloque;
                $consultados[] = $modulo;
            }
        }

        $texto = implode("\n\n", $bloques);
        if ($denegados !== []) {
            $texto .= "\n\n[Acceso] El usuario NO tiene permiso para consultar: " . implode(', ', $denegados)
                . '. No proporciones ni supongas datos de esos módulos; indica que no tiene acceso.';
        }

        return ['texto' => trim($texto), 'consultados' => $consultados, 'denegados' => $denegados];
    }

    public function activos(Usuario $usuario): string
    {
        $base = fn () => $this->acceso->acotarActivos($usuario, Activo::query());
        $total = $base()->count();
        if ($total === 0) {
            return "[Activos] No hay activos registrados dentro del alcance del usuario.";
        }
        $porTipo = $base()->selectRaw('tipo, COUNT(*) as n')->groupBy('tipo')->pluck('n', 'tipo')->all();
        $porCriticidad = $base()->selectRaw('criticidad_calculada, COUNT(*) as n')->groupBy('criticidad_calculada')->pluck('n', 'criticidad_calculada')->all();
        $sinResponsable = $base()->whereNull('responsable_id')->count();
        $criticos = $base()->with('departamento:id,nombre')->orderByDesc('criticidad_calculada')->limit(self::TOP)->get()
            ->map(fn (Activo $a) => "- {$a->nombre} | tipo {$a->tipo->value} | criticidad {$a->criticidad_calculada} (C{$a->confidencialidad}/I{$a->integridad}/D{$a->disponibilidad})"
                . ($a->departamento ? " | depto. {$a->departamento->nombre}" : ''))
            ->implode("\n");

        return "[Activos] Total: {$total}. Por tipo: " . $this->par($porTipo) . '. Por criticidad (1-5): ' . $this->par($porCriticidad)
            . ". Sin responsable asignado: {$sinResponsable}.\nActivos más críticos:\n{$criticos}";
    }

    public function riesgos(Usuario $usuario): string
    {
        $base = fn () => Riesgo::query()->whereHas('activo', fn ($q) => $this->acceso->acotarActivos($usuario, $q));
        $total = $base()->count();
        if ($total === 0) {
            return '[Riesgos] No hay riesgos registrados.';
        }
        $porNivel = $base()->selectRaw('nivel_calculado, COUNT(*) as n')->groupBy('nivel_calculado')->pluck('n', 'nivel_calculado')->all();
        $sinTratamiento = $base()->whereNull('tipo_tratamiento')->count();
        $revisionVencida = $base()->whereNotNull('fecha_revision')->whereDate('fecha_revision', '<', now())->count();
        $principales = $base()->with(['activo:id,nombre', 'amenaza:id,nombre', 'vulnerabilidad:id,nombre'])
            ->orderByRaw("FIELD(nivel_calculado, 'critico', 'alto', 'medio', 'bajo')")
            ->orderByDesc('probabilidad')->limit(self::TOP)->get()
            ->map(fn (Riesgo $r) => "- Activo {$r->activo?->nombre} | amenaza {$r->amenaza?->nombre} | vulnerabilidad " . ($r->vulnerabilidad?->nombre ?? 'n/d')
                . " | P{$r->probabilidad} x I{$r->impacto} = {$r->nivel_calculado?->value} | tratamiento " . ($r->tipo_tratamiento?->value ?? 'sin definir')
                . ($r->nivel_residual ? " | residual {$r->nivel_residual}" : ''))
            ->implode("\n");

        return "[Riesgos] Total: {$total}. Por nivel: " . $this->par($porNivel) . ". Sin tratamiento: {$sinTratamiento}. Con revisión vencida: {$revisionVencida}."
            . "\nRiesgos prioritarios (metodología: nivel = matriz 5x5 de probabilidad x impacto):\n{$principales}";
    }

    public function incidentes(Usuario $usuario): string
    {
        $total = Incidente::query()->count();
        if ($total === 0) {
            return '[Incidentes] No hay incidentes registrados.';
        }
        $porEstado = Incidente::query()->selectRaw('estado, COUNT(*) as n')->groupBy('estado')->pluck('n', 'estado')->all();
        $abiertos = Incidente::query()->with('activo:id,nombre')->where('estado', '!=', 'cerrado')
            ->orderBy('fecha_deteccion')->limit(self::TOP)->get()
            ->map(fn (Incidente $i) => "- {$i->titulo} | estado {$i->estado->value} | detectado " . $i->fecha_deteccion?->format('Y-m-d')
                . ' (' . (int) $i->fecha_deteccion?->diffInDays(now()) . ' días)' . ($i->activo ? " | activo {$i->activo->nombre}" : ''))
            ->implode("\n");

        return "[Incidentes] Total: {$total}. Por estado: " . $this->par($porEstado) . '.'
            . ($abiertos !== '' ? "\nIncidentes no cerrados (más antiguos primero):\n{$abiertos}" : "\nNo hay incidentes pendientes.");
    }

    public function auditorias(): string
    {
        $total = Auditoria::query()->count();
        if ($total === 0) {
            return '[Auditorías] No hay auditorías registradas.';
        }
        $hallazgos = Hallazgo::query()->selectRaw('severidad, COUNT(*) as n')->groupBy('severidad')->pluck('n', 'severidad')->all();
        $ncAbiertas = NoConformidad::query()->whereIn('estado', ['abierta', 'en_proceso', 'vencida'])->count();
        $acVencidas = AccionCorrectiva::query()->where('estado', '!=', 'cerrada')->whereDate('fecha_compromiso', '<', now())->count();
        $recientes = Auditoria::query()->withCount('hallazgos')->orderByDesc('fecha_inicio')->limit(5)->get()
            ->map(fn (Auditoria $a) => "- {$a->alcance} | " . $a->fecha_inicio?->format('Y-m-d') . ' → ' . ($a->fecha_fin?->format('Y-m-d') ?? 'en curso') . " | hallazgos: {$a->hallazgos_count}")
            ->implode("\n");

        return "[Auditorías] Total: {$total}. Hallazgos por severidad: " . $this->par($hallazgos)
            . ". No conformidades abiertas: {$ncAbiertas}. Acciones correctivas vencidas: {$acVencidas}.\nAuditorías recientes:\n{$recientes}";
    }

    public function documentos(): string
    {
        $total = Documento::query()->count();
        if ($total === 0) {
            return '[Documentos] No hay documentos registrados.';
        }
        $porEstado = Documento::query()->selectRaw('estado, COUNT(*) as n')->groupBy('estado')->pluck('n', 'estado')->all();
        $lista = Documento::query()->orderByDesc('updated_at')->limit(10)->get()
            ->map(fn (Documento $d) => "- {$d->titulo} | estado {$d->estado->value} | versión {$d->version_actual}")
            ->implode("\n");

        return "[Documentos] Total: {$total}. Por estado: " . $this->par($porEstado) . ".\nDocumentos:\n{$lista}";
    }

    public function cumplimiento(string $mensaje = ''): string
    {
        $porEstado = ControlIso::query()->selectRaw('estado_implementacion, COUNT(*) as n')->groupBy('estado_implementacion')->pluck('n', 'estado_implementacion')->all();
        $texto = '[Cumplimiento ISO/IEC 27001:2022 Anexo A] Controles por estado de implementación: ' . $this->par($porEstado) . '.';

        // Si la pregunta cita controles concretos (A.8.13…), se agregan con su nombre oficial del catálogo.
        if (preg_match_all('/\bA\.(\d{1,2})\.(\d{1,2})\b/i', $mensaje, $m)) {
            $codigos = array_unique(array_map(fn ($a, $b) => "A.{$a}.{$b}", $m[1], $m[2]));
            $controles = ControlIso::query()->whereIn('codigo', $codigos)->get();
            foreach ($controles as $c) {
                $texto .= "\n- {$c->codigo} {$c->nombre} | categoría {$c->categoria?->value} | estado {$c->estado_implementacion?->value}";
            }
            $faltantes = array_diff($codigos, $controles->pluck('codigo')->all());
            if ($faltantes !== []) {
                $texto .= "\n- No existen en el catálogo del Anexo A: " . implode(', ', $faltantes) . '.';
            }
        }

        return $texto;
    }

    /** Catálogo oficial del Anexo A (código y nombre) para validar sugerencias. */
    public function catalogoControles(): string
    {
        return ControlIso::query()->orderBy('id')->get(['codigo', 'nombre'])
            ->map(fn ($c) => "{$c->codigo} {$c->nombre}")->implode('; ');
    }

    private function par(array $valores): string
    {
        if ($valores === []) {
            return 'ninguno';
        }

        return collect($valores)->map(fn ($n, $k) => Str::of((string) ($k === '' ? 'sin dato' : $k))->replace('_', ' ') . ": {$n}")->implode(', ');
    }
}
