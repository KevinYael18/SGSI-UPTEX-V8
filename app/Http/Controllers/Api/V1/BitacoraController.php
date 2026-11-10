<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BitacoraController extends Controller
{
    private const LIMITE_EXPORTACION = 5000;

    #[OA\Get(path: '/api/v1/bitacora', tags: ['Bitacora'], summary: 'Lista el historial completo de actividad del sistema, filtrable por usuario/entidad/acción/fechas')]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('gestionar-roles');

        $registros = $this->consultaFiltrada($request)
            ->latest('created_at')
            ->paginate((int) $request->integer('por_pagina', 25));

        return response()->json([
            'data' => collect($registros->items())->map($this->transformarFila(...)),
            'meta' => [
                'current_page' => $registros->currentPage(),
                'last_page' => $registros->lastPage(),
                'per_page' => $registros->perPage(),
                'total' => $registros->total(),
            ],
        ]);
    }

    #[OA\Get(path: '/api/v1/bitacora/entidades', tags: ['Bitacora'], summary: 'Lista los tipos de entidad distintos presentes en la bitácora (para el filtro)')]
    public function entidadesDisponibles(): JsonResponse
    {
        Gate::authorize('gestionar-roles');

        return response()->json([
            'data' => ActivityLog::query()->distinct()->orderBy('entidad')->pluck('entidad'),
        ]);
    }

    #[OA\Get(path: '/api/v1/bitacora.pdf', tags: ['Bitacora'], summary: 'Exporta la bitácora filtrada a PDF (máximo 5000 filas)')]
    public function exportarPdf(Request $request): Response
    {
        Gate::authorize('gestionar-roles');

        $registros = $this->consultaFiltrada($request)->latest('created_at')->limit(self::LIMITE_EXPORTACION)->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reportes.bitacora', ['registros' => $registros])->output();

        return response($pdf, 200)->header('Content-Type', 'application/pdf');
    }

    #[OA\Get(path: '/api/v1/bitacora.xlsx', tags: ['Bitacora'], summary: 'Exporta la bitácora filtrada a Excel (máximo 5000 filas)')]
    public function exportarExcel(Request $request): Response
    {
        Gate::authorize('gestionar-roles');

        $registros = $this->consultaFiltrada($request)->latest('created_at')->limit(self::LIMITE_EXPORTACION)->get();

        $hoja = new Spreadsheet();
        $activa = $hoja->getActiveSheet();
        $activa->setTitle('Bitacora');
        $activa->fromArray(['Fecha', 'Entidad', 'ID', 'Acción', 'Usuario'], null, 'A1');

        foreach ($registros as $i => $r) {
            $fila = $i + 2;
            $activa->fromArray([
                $r->created_at->format('d/m/Y H:i'),
                $r->entidad,
                $r->entidad_id,
                $r->accion,
                $r->usuario?->nombre ?? 'Sistema',
            ], null, "A{$fila}");
        }

        $writer = new Xlsx($hoja);
        $rutaTemporal = tempnam(sys_get_temp_dir(), 'sgsi_xlsx_');
        $writer->save($rutaTemporal);
        $contenido = file_get_contents($rutaTemporal);
        unlink($rutaTemporal);

        return response($contenido, 200)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="bitacora.xlsx"');
    }

    private function consultaFiltrada(Request $request): Builder
    {
        return ActivityLog::query()
            ->with('usuario')
            ->when($request->filled('entidad'), fn ($q) => $q->where('entidad', $request->string('entidad')))
            ->when($request->filled('accion'), fn ($q) => $q->where('accion', $request->string('accion')))
            ->when($request->filled('usuario_id'), fn ($q) => $q->where('usuario_id', $request->integer('usuario_id')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('hasta')));
    }

    private function transformarFila(ActivityLog $r): array
    {
        return [
            'id' => $r->id,
            'entidad' => $r->entidad,
            'entidad_id' => $r->entidad_id,
            'accion' => $r->accion,
            'usuario' => $r->usuario?->nombre ?? 'Sistema',
            'cambios' => $r->cambios,
            'creado_en' => $r->created_at->toIso8601String(),
        ];
    }
}