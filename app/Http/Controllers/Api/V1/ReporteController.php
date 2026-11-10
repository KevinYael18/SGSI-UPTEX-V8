<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activo;
use App\Models\Riesgo;
use App\Services\IndicadorService;
use App\Services\ReporteService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReporteController extends Controller
{
    public function __construct(
        private readonly ReporteService $service,
        private readonly IndicadorService $indicadores,
    ) {
    }

    #[OA\Get(path: '/api/v1/reportes/activos.pdf', tags: ['Reportes'], summary: 'Reporte PDF de inventario de activos con identidad institucional')]
    public function activos(Request $request): Response
    {
        $this->authorize('viewAny', Activo::class);

        $pdf = $this->service->activosPdf($request->user()->id, $request->only('departamento_id'));

        return response($pdf, 200)->header('Content-Type', 'application/pdf');
    }

    #[OA\Get(path: '/api/v1/reportes/riesgos.pdf', tags: ['Reportes'], summary: 'Reporte PDF de riesgos con identidad institucional')]
    public function riesgos(Request $request): Response
    {
        $this->authorize('viewAny', Riesgo::class);

        $pdf = $this->service->riesgosPdf($request->user()->id, $request->only('nivel_calculado'));

        return response($pdf, 200)->header('Content-Type', 'application/pdf');
    }

    #[OA\Get(path: '/api/v1/reportes/activos.xlsx', tags: ['Reportes'], summary: 'Reporte Excel de inventario de activos')]
    public function activosExcel(Request $request): Response
    {
        $this->authorize('viewAny', Activo::class);

        $activos = Activo::query()
            ->with(['departamento', 'ubicacion', 'responsable'])
            ->when($request->filled('departamento_id'), fn ($q) => $q->where('departamento_id', $request->integer('departamento_id')))
            ->get();

        $hoja = new Spreadsheet();
        $activa = $hoja->getActiveSheet();
        $activa->setTitle('Activos');
        $activa->fromArray(['Nombre', 'Tipo', 'Departamento', 'Ubicación', 'Responsable', 'Confidencialidad', 'Integridad', 'Disponibilidad', 'Criticidad'], null, 'A1');

        foreach ($activos as $i => $activo) {
            $fila = $i + 2;
            $activa->fromArray([
                $activo->nombre,
                $activo->tipo->value,
                $activo->departamento?->nombre,
                $activo->ubicacion?->nombre,
                $activo->responsable?->nombre,
                $activo->confidencialidad,
                $activo->integridad,
                $activo->disponibilidad,
                $activo->criticidad_calculada,
            ], null, "A{$fila}");
        }

        return $this->descargarXlsx($hoja, 'reporte-activos.xlsx');
    }

    #[OA\Get(path: '/api/v1/reportes/riesgos.xlsx', tags: ['Reportes'], summary: 'Reporte Excel de riesgos')]
    public function riesgosExcel(Request $request): Response
    {
        $this->authorize('viewAny', Riesgo::class);

        $riesgos = Riesgo::query()
            ->with(['activo', 'amenaza', 'vulnerabilidad'])
            ->when($request->filled('nivel_calculado'), fn ($q) => $q->where('nivel_calculado', $request->string('nivel_calculado')))
            ->get();

        $hoja = new Spreadsheet();
        $activa = $hoja->getActiveSheet();
        $activa->setTitle('Riesgos');
        $activa->fromArray(['Activo', 'Amenaza', 'Vulnerabilidad', 'Probabilidad', 'Impacto', 'Nivel', 'Tratamiento'], null, 'A1');

        foreach ($riesgos as $i => $riesgo) {
            $fila = $i + 2;
            $activa->fromArray([
                $riesgo->activo?->nombre,
                $riesgo->amenaza?->nombre,
                $riesgo->vulnerabilidad?->nombre,
                $riesgo->probabilidad,
                $riesgo->impacto,
                $riesgo->nivel_calculado?->value,
                $riesgo->tipo_tratamiento?->value ?? 'Sin definir',
            ], null, "A{$fila}");
        }

        return $this->descargarXlsx($hoja, 'reporte-riesgos.xlsx');
    }

    #[OA\Get(path: '/api/v1/reportes/indicadores.pdf', tags: ['Reportes'], summary: 'Reporte PDF consolidado de indicadores ejecutivos')]
    public function indicadoresPdf(Request $request): Response
    {
        Gate::authorize('ver-dashboard');

        $datos = [
            ...$this->indicadores->resumen(),
            'incidentes_por_mes' => $this->indicadores->incidentesPorMes(),
            'riesgos_por_nivel' => $this->indicadores->riesgosPorNivel(),
        ];

        $pdf = $this->service->indicadoresPdf($request->user()->id, $datos);

        return response($pdf, 200)->header('Content-Type', 'application/pdf');
    }

    #[OA\Get(path: '/api/v1/reportes/indicadores.xlsx', tags: ['Reportes'], summary: 'Reporte Excel consolidado de indicadores ejecutivos')]
    public function indicadoresExcel(Request $request): Response
    {
        Gate::authorize('ver-dashboard');

        $resumen = $this->indicadores->resumen();
        $porMes = $this->indicadores->incidentesPorMes();
        $porNivel = $this->indicadores->riesgosPorNivel();

        $hoja = new Spreadsheet();
        $activa = $hoja->getActiveSheet();
        $activa->setTitle('Indicadores');
        $activa->fromArray(['Indicador', 'Valor'], null, 'A1');
        $activa->fromArray([
            ['Índice de cumplimiento ISO/IEC 27001:2022', "{$resumen['indice_cumplimiento']}%"],
            ['Riesgos en nivel crítico', $resumen['riesgos_criticos']],
            ['Incidentes actualmente abiertos', $resumen['incidentes_abiertos']],
            ['Controles del Anexo A implementados', "{$resumen['controles_implementados']} / {$resumen['controles_totales']}"],
        ], null, 'A2');

        $hojaMeses = $hoja->createSheet();
        $hojaMeses->setTitle('Incidentes por mes');
        $hojaMeses->fromArray(['Mes', 'Incidentes'], null, 'A1');
        $filaMes = 2;
        foreach ($porMes as $mes => $total) {
            $hojaMeses->fromArray([$mes, $total], null, "A{$filaMes}");
            $filaMes++;
        }

        $hojaNiveles = $hoja->createSheet();
        $hojaNiveles->setTitle('Riesgos por nivel');
        $hojaNiveles->fromArray(['Nivel', 'Cantidad'], null, 'A1');
        $filaNivel = 2;
        foreach ($porNivel as $nivel => $total) {
            $hojaNiveles->fromArray([$nivel, $total], null, "A{$filaNivel}");
            $filaNivel++;
        }

        $hoja->setActiveSheetIndex(0);
        $this->service->registrarExportacion($request->user()->id, 'indicadores', [], 'excel');

        return $this->descargarXlsx($hoja, 'reporte-indicadores.xlsx');
    }

    private function descargarXlsx(Spreadsheet $hoja, string $nombreArchivo): Response
    {
        $writer = new Xlsx($hoja);
        $rutaTemporal = tempnam(sys_get_temp_dir(), 'sgsi_xlsx_');
        $writer->save($rutaTemporal);
        $contenido = file_get_contents($rutaTemporal);
        unlink($rutaTemporal);

        return response($contenido, 200)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', "attachment; filename=\"{$nombreArchivo}\"");
    }
}