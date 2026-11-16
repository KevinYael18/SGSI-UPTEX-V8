<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarComputadoraRequest;
use App\Http\Requests\InstalacionSoftwareRequest;
use App\Http\Requests\MantenimientoEquipoRequest;
use App\Http\Resources\ComputadoraResource;
use App\Http\Responses\ApiResponse;
use App\Models\Computadora;
use App\Models\Edificio;
use App\Models\Laboratorio;
use App\Models\Software;
use App\Services\ExportacionInventarioService;
use App\Services\ImportacionComputadorasService;
use App\Services\InventarioService;
use App\Support\InventarioCatalogos as C;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

/**
 * Computadoras del inventario tecnológico. La autorización por registro usa
 * la ActivoPolicy del activo asociado (incluye el alcance por departamento
 * del rol Consulta y la edición propia del Responsable de Activo).
 */
class ComputadoraController extends Controller
{
    public function __construct(private readonly InventarioService $inventario)
    {
    }

    #[OA\Get(path: '/api/v1/computadoras', tags: ['Inventario'], summary: 'Lista paginada de computadoras con filtros')]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('inventario.ver');

        $equipos = $this->filtrar($request)
            ->with(['laboratorio.edificio', 'software', 'activo'])
            ->orderBy('nombre_equipo')
            ->paginate(min(100, max(5, $request->integer('por_pagina', 15))));

        return ApiResponse::coleccion(ComputadoraResource::collection($equipos));
    }

    #[OA\Get(path: '/api/v1/computadoras/{computadora}', tags: ['Inventario'], summary: 'Ficha técnica completa de una computadora')]
    public function show(Computadora $computadora): JsonResponse
    {
        $this->authorize('view', $computadora->activo);

        $computadora->load([
            'laboratorio.edificio', 'software', 'mantenimientos.registradoPor',
            'activo' => fn ($q) => $q->withCount(['riesgos', 'incidentes'])->with(['departamento', 'responsable', 'controlesIso']),
        ]);

        return ApiResponse::item(new ComputadoraResource($computadora));
    }

    #[OA\Post(path: '/api/v1/computadoras', tags: ['Inventario'], summary: 'Registra una computadora (crea su activo o vincula uno existente de hardware)')]
    public function store(GuardarComputadoraRequest $request): JsonResponse
    {
        Gate::authorize('inventario.gestionar');
        $computadora = $this->inventario->crearComputadora($request->validated(), $request->user());

        return ApiResponse::creado(new ComputadoraResource($computadora->load(['laboratorio.edificio', 'activo'])));
    }

    #[OA\Put(path: '/api/v1/computadoras/{computadora}', tags: ['Inventario'], summary: 'Actualiza la ficha técnica (y datos del activo asociado)')]
    public function update(GuardarComputadoraRequest $request, Computadora $computadora): JsonResponse
    {
        $this->authorize('update', $computadora->activo);
        $actualizada = $this->inventario->actualizarComputadora($computadora, $request->validated(), $request->user());

        return ApiResponse::item(new ComputadoraResource($actualizada->load(['laboratorio.edificio', 'activo'])));
    }

    public function destroy(Request $request, Computadora $computadora): JsonResponse
    {
        $this->authorize('delete', $computadora->activo);
        $this->inventario->eliminarComputadora($computadora, $request->user());

        return ApiResponse::sinContenido();
    }

    // ------------------------------------------------------------- Software

    public function instalarSoftware(InstalacionSoftwareRequest $request, Computadora $computadora): JsonResponse
    {
        $this->authorize('update', $computadora->activo);
        $software = Software::query()->findOrFail($request->integer('software_id'));
        $this->inventario->instalarSoftware($computadora, $software, $request->validated(), $request->user());

        return $this->show($computadora->fresh());
    }

    public function actualizarSoftware(InstalacionSoftwareRequest $request, Computadora $computadora, Software $software): JsonResponse
    {
        $this->authorize('update', $computadora->activo);
        $this->inventario->actualizarInstalacion($computadora, $software, $request->validated(), $request->user());

        return $this->show($computadora->fresh());
    }

    public function quitarSoftware(Request $request, Computadora $computadora, Software $software): JsonResponse
    {
        $this->authorize('update', $computadora->activo);
        $this->inventario->quitarSoftware($computadora, $software, $request->user());

        return $this->show($computadora->fresh());
    }

    // ---------------------------------------------------------- Mantenimiento

    public function registrarMantenimiento(MantenimientoEquipoRequest $request, Computadora $computadora): JsonResponse
    {
        $this->authorize('update', $computadora->activo);
        $this->inventario->registrarMantenimiento($computadora, $request->validated(), $request->user());

        return $this->show($computadora->fresh());
    }

    // ------------------------------------------------ Importación / exportación

    #[OA\Get(path: '/api/v1/computadoras/exportar.xlsx', tags: ['Inventario'], summary: 'Excel: equipos, software, antivirus y equipos que requieren atención (filtra por edificio o laboratorio)')]
    public function exportar(Request $request, ExportacionInventarioService $exportacion): Response
    {
        Gate::authorize('inventario.ver');

        $equipos = $this->filtrar($request)
            ->with(['laboratorio.edificio', 'software', 'mantenimientos', 'activo' => fn ($q) => $q->withCount('riesgos')->with(['departamento', 'responsable'])])
            ->get()
            ->sortBy(fn ($c) => [$c->laboratorio?->edificio?->nombre ?? '~', $c->laboratorio?->nombre ?? '~', $c->nombre_equipo])
            ->values();

        $alcance = 'todo el inventario';
        $sufijo = 'completo';
        if ($request->filled('laboratorio_id') && ($lab = Laboratorio::query()->with('edificio')->find($request->integer('laboratorio_id')))) {
            $alcance = $lab->nombreCompleto();
            $sufijo = 'laboratorio-' . str($lab->nombre)->slug();
        } elseif ($request->filled('edificio_id') && ($ed = Edificio::query()->find($request->integer('edificio_id')))) {
            $alcance = $ed->nombre;
            $sufijo = 'edificio-' . str($ed->nombre)->slug();
        }

        return $this->xlsx($exportacion->contenido($exportacion->libro($equipos, $alcance)), "inventario-{$sufijo}.xlsx");
    }

    public function plantilla(ExportacionInventarioService $exportacion): Response
    {
        Gate::authorize('inventario.gestionar');

        return $this->xlsx($exportacion->contenido($exportacion->plantillaImportacion()), 'plantilla-importacion-computadoras.xlsx');
    }

    #[OA\Post(path: '/api/v1/computadoras/importar/analizar', tags: ['Inventario'], summary: 'Vista previa de una importación (no guarda nada)')]
    public function analizarImportacion(Request $request, ImportacionComputadorasService $importacion): JsonResponse
    {
        Gate::authorize('inventario.gestionar');
        $request->validate($this->reglasArchivo());

        $analisis = $importacion->analizar($request->file('archivo'), $request->integer('laboratorio_id') ?: null);
        $analisis['filas'] = array_map(fn ($f) => array_diff_key($f, ['_datos' => 1, '_software' => 1]), $analisis['filas']);

        return response()->json(['data' => $analisis]);
    }

    #[OA\Post(path: '/api/v1/computadoras/importar', tags: ['Inventario'], summary: 'Importa computadoras; los duplicados se omiten salvo autorización expresa')]
    public function importar(Request $request, ImportacionComputadorasService $importacion): JsonResponse
    {
        Gate::authorize('inventario.gestionar');
        $opciones = $request->validate($this->reglasArchivo() + [
            'confidencialidad' => ['required', 'integer', 'between:1,5'],
            'integridad' => ['required', 'integer', 'between:1,5'],
            'disponibilidad' => ['required', 'integer', 'between:1,5'],
            'departamento_id' => ['nullable', 'integer', 'exists:departamentos,id'],
            'duplicados' => ['required', 'in:omitir,actualizar'],
            'controles_iso_ids' => ['sometimes', 'array'],
            'controles_iso_ids.*' => ['integer', 'exists:controles_iso,id'],
        ], [
            'confidencialidad.required' => 'Indica la valoración de confidencialidad para los equipos nuevos.',
            'integridad.required' => 'Indica la valoración de integridad para los equipos nuevos.',
            'disponibilidad.required' => 'Indica la valoración de disponibilidad para los equipos nuevos.',
            'duplicados.required' => 'Indica qué hacer con los duplicados.',
        ]);

        $resultado = $importacion->importar($request->file('archivo'), $opciones, $request->user());

        return response()->json(['data' => $resultado]);
    }

    private function reglasArchivo(): array
    {
        $maxKb = (int) config('sgsi.inventario.importacion_max_kb', 4096);

        return [
            'archivo' => ['required', 'file', "max:{$maxKb}", 'mimes:xlsx,csv,txt'],
            'laboratorio_id' => ['nullable', 'integer', 'exists:laboratorios,id'],
        ];
    }

    /** Filtros comunes del listado y la exportación. */
    private function filtrar(Request $request)
    {
        $buscar = trim((string) $request->input('buscar', ''));

        return $this->inventario->visiblesPara($request->user())
            ->when($request->filled('laboratorio_id'), fn ($q) => $q->where('laboratorio_id', $request->integer('laboratorio_id')))
            ->when($request->filled('edificio_id'), fn ($q) => $q->whereHas('laboratorio', fn ($l) => $l->where('edificio_id', $request->integer('edificio_id'))))
            ->when($request->boolean('sin_laboratorio'), fn ($q) => $q->whereNull('laboratorio_id'))
            ->when(in_array($request->input('estado_operativo'), array_keys(C::ESTADOS_OPERATIVOS), true),
                fn ($q) => $q->where('estado_operativo', $request->input('estado_operativo')))
            ->when($request->filled('so_nombre'), fn ($q) => $q->where('so_nombre', $request->input('so_nombre')))
            ->when($request->filled('software_id'), fn ($q) => $q->whereHas('software', fn ($s) => $s->whereKey($request->integer('software_id'))))
            ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nombre_equipo', 'like', "%{$buscar}%")
                ->orWhere('numero_inventario', 'like', "%{$buscar}%")
                ->orWhere('numero_serie', 'like', "%{$buscar}%")
                ->orWhere('direccion_mac', 'like', "%{$buscar}%")
                ->orWhere('direccion_ip', 'like', "%{$buscar}%")));
    }

    private function xlsx(string $contenido, string $nombre): Response
    {
        return response($contenido, 200)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', "attachment; filename=\"{$nombre}\"");
    }
}
