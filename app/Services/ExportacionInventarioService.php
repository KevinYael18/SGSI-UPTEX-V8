<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Computadora;
use App\Models\InstalacionSoftware;
use App\Support\InventarioCatalogos as C;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Reportes Excel del inventario tecnológico. Un libro con cuatro hojas:
 * Equipos, Software instalado, Antivirus y Requieren atención (mantenimiento,
 * fuera de servicio, SO sin soporte, antivirus o software pendiente).
 * También genera la plantilla de importación.
 */
class ExportacionInventarioService
{
    /** @param Collection<int, Computadora> $equipos */
    public function libro(Collection $equipos, string $alcance): Spreadsheet
    {
        $libro = new Spreadsheet();
        $libro->getProperties()->setCreator('SGSI UPTex')->setTitle("Inventario tecnológico — {$alcance}");

        $this->hojaEquipos($libro->getActiveSheet(), $equipos);
        $this->hojaSoftware($libro->createSheet(), $equipos);
        $this->hojaAntivirus($libro->createSheet(), $equipos);
        $this->hojaAtencion($libro->createSheet(), $equipos);
        $libro->setActiveSheetIndex(0);

        return $libro;
    }

    public function plantillaImportacion(): Spreadsheet
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet()->setTitle('Computadoras');
        $hoja->fromArray(ImportacionComputadorasService::COLUMNAS, null, 'A1');
        $this->estilizarEncabezado($hoja, count(ImportacionComputadorasService::COLUMNAS));

        $ayuda = $libro->createSheet()->setTitle('Instrucciones');
        $lineas = [
            ['Columna', 'Descripción / valores permitidos'],
            ['nombre_equipo', 'OBLIGATORIO. Nombre del equipo (p. ej. el hostname).'],
            ['numero_inventario / numero_serie / direccion_mac', 'Se usan para detectar duplicados. La MAC acepta 001A2B3C4D5E o 00:1A:2B:3C:4D:5E.'],
            ['edificio / laboratorio', 'Deben existir ya en SGSI UPTex (no se crean automáticamente). También puedes elegir un laboratorio para todo el archivo al importar.'],
            ['fechas', 'AAAA-MM-DD o DD/MM/AAAA.'],
            ['ram_gb / almacenamiento_gb', 'Números enteros en GB.'],
            ['software', 'Programas del catálogo separados por punto y coma; versión opcional con @. Ej.: Google Chrome@120.0; Visual Studio Code'],
        ];
        foreach ([
            'estado_operativo' => C::ESTADOS_OPERATIVOS, 'tipo_almacenamiento' => C::TIPOS_ALMACENAMIENTO,
            'so_arquitectura' => C::ARQUITECTURAS, 'so_activacion' => C::ACTIVACION_SO, 'so_soporte' => C::SOPORTE_SO,
            'antivirus_instalacion' => C::INSTALACION_ANTIVIRUS, 'antivirus_proteccion' => C::PROTECCION_ANTIVIRUS,
        ] as $columna => $catalogo) {
            $lineas[] = [$columna, implode(' | ', array_keys($catalogo))];
        }
        $lineas[] = ['', 'Si un dato no se conoce, deja la celda vacía: el sistema lo registrará como desconocido o pendiente de verificación.'];
        $ayuda->fromArray($lineas, null, 'A1');
        $this->estilizarEncabezado($ayuda, 2);
        $ayuda->getColumnDimension('A')->setWidth(42);
        $ayuda->getColumnDimension('B')->setWidth(110);
        $libro->setActiveSheetIndex(0);

        return $libro;
    }

    public function contenido(Spreadsheet $libro): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'sgsi_inv_');
        (new Xlsx($libro))->save($ruta);
        $contenido = (string) file_get_contents($ruta);
        @unlink($ruta);

        return $contenido;
    }

    private function hojaEquipos(Worksheet $hoja, Collection $equipos): void
    {
        $hoja->setTitle('Equipos');
        $encabezados = ['Edificio', 'Laboratorio', 'Equipo', 'No. inventario', 'No. serie', 'MAC', 'IP', 'Ubicación física', 'Estado',
            'Fabricante', 'Modelo', 'Procesador', 'RAM (GB)', 'Almacenamiento (GB)', 'Tipo almacenamiento', 'Tarjeta gráfica',
            'Adquisición', 'Garantía hasta', 'Sistema operativo', 'Edición', 'Versión SO', 'Arquitectura', 'Activación', 'Última actualización SO',
            'Soporte SO', 'Antivirus', 'Versión antivirus', 'Estado antivirus', 'Programas registrados', 'Programas por actualizar',
            'Departamento', 'Responsable', 'Criticidad', 'Riesgos registrados', 'Observaciones'];
        $hoja->fromArray($encabezados, null, 'A1');
        $fila = 2;
        foreach ($equipos as $c) {
            $hoja->fromArray([
                $c->laboratorio?->edificio?->nombre, $c->laboratorio?->nombre, $c->nombre_equipo, $c->numero_inventario, $c->numero_serie,
                $c->direccion_mac, $c->direccion_ip, $c->ubicacion_fisica, C::ESTADOS_OPERATIVOS[$c->estado_operativo] ?? $c->estado_operativo,
                $c->fabricante, $c->modelo, $c->procesador, $c->ram_gb, $c->almacenamiento_gb,
                C::TIPOS_ALMACENAMIENTO[$c->tipo_almacenamiento] ?? null, $c->tarjeta_grafica,
                $c->fecha_adquisicion?->toDateString(), $c->garantia_hasta?->toDateString(), $c->so_nombre, $c->so_edicion, $c->so_version,
                C::ARQUITECTURAS[$c->so_arquitectura] ?? null, C::ACTIVACION_SO[$c->so_activacion] ?? null, $c->so_ultima_actualizacion?->toDateString(),
                C::SOPORTE_SO[$c->so_soporte] ?? null, $c->antivirus_nombre, $c->antivirus_version, $this->etiquetaAntivirus($c->estadoAntivirus()),
                $c->software->count(), $c->softwarePendienteActualizacion(),
                $c->activo?->departamento?->nombre, $c->activo?->responsable?->nombre, $c->activo?->criticidad_calculada,
                $c->activo?->riesgos_count ?? null, $c->observaciones,
            ], null, "A{$fila}", true);
            $fila++;
        }
        $this->estilizarEncabezado($hoja, count($encabezados));
    }

    private function hojaSoftware(Worksheet $hoja, Collection $equipos): void
    {
        $hoja->setTitle('Software instalado');
        $encabezados = ['Edificio', 'Laboratorio', 'Equipo', 'No. inventario', 'Programa', 'Fabricante', 'Categoría', 'Versión instalada',
            'Versión vigente', 'Requiere actualización', 'Autorización', 'Tipo de licencia', 'Estado de licencia', 'Fecha de instalación', 'Observaciones'];
        $hoja->fromArray($encabezados, null, 'A1');
        $fila = 2;
        foreach ($equipos as $c) {
            foreach ($c->software as $s) {
                $hoja->fromArray([
                    $c->laboratorio?->edificio?->nombre, $c->laboratorio?->nombre, $c->nombre_equipo, $c->numero_inventario,
                    $s->nombre, $s->fabricante, C::CATEGORIAS_SOFTWARE[$s->categoria] ?? $s->categoria, $s->pivot->version, $s->version_vigente,
                    InstalacionSoftware::estaDesactualizada((bool) $s->pivot->requiere_actualizacion, $s->pivot->version, $s->version_vigente) ? 'Sí' : 'No',
                    C::ESTADOS_AUTORIZACION[$s->estado_autorizacion] ?? $s->estado_autorizacion,
                    C::TIPOS_LICENCIA[$s->tipo_licencia] ?? $s->tipo_licencia,
                    C::ESTADOS_LICENCIA[$s->pivot->estado_licencia] ?? $s->pivot->estado_licencia,
                    $s->pivot->fecha_instalacion ? substr((string) $s->pivot->fecha_instalacion, 0, 10) : null,
                    $s->pivot->observaciones,
                ], null, "A{$fila}", true);
                $fila++;
            }
        }
        $this->estilizarEncabezado($hoja, count($encabezados));
    }

    private function hojaAntivirus(Worksheet $hoja, Collection $equipos): void
    {
        $hoja->setTitle('Antivirus');
        $encabezados = ['Edificio', 'Laboratorio', 'Equipo', 'No. inventario', 'Antivirus', 'Fabricante', 'Versión', 'Instalación', 'Protección', 'Última actualización', 'Estado'];
        $hoja->fromArray($encabezados, null, 'A1');
        $fila = 2;
        foreach ($equipos as $c) {
            $hoja->fromArray([
                $c->laboratorio?->edificio?->nombre, $c->laboratorio?->nombre, $c->nombre_equipo, $c->numero_inventario,
                $c->antivirus_nombre, $c->antivirus_fabricante, $c->antivirus_version,
                C::INSTALACION_ANTIVIRUS[$c->antivirus_instalacion] ?? null, C::PROTECCION_ANTIVIRUS[$c->antivirus_proteccion] ?? null,
                $c->antivirus_ultima_actualizacion?->toDateString(), $this->etiquetaAntivirus($c->estadoAntivirus()),
            ], null, "A{$fila}", true);
            $fila++;
        }
        $this->estilizarEncabezado($hoja, count($encabezados));
    }

    private function hojaAtencion(Worksheet $hoja, Collection $equipos): void
    {
        $hoja->setTitle('Requieren atención');
        $encabezados = ['Edificio', 'Laboratorio', 'Equipo', 'No. inventario', 'Estado', 'Motivos', 'Último mantenimiento'];
        $hoja->fromArray($encabezados, null, 'A1');
        $fila = 2;
        foreach ($equipos as $c) {
            $motivos = [];
            if (in_array($c->estado_operativo, ['mantenimiento', 'fuera_servicio'], true)) {
                $motivos[] = C::ESTADOS_OPERATIVOS[$c->estado_operativo];
            }
            if ($c->so_soporte === 'sin_soporte') {
                $motivos[] = 'Sistema operativo sin soporte';
            }
            if ($c->estadoAntivirus() === 'desactualizado') {
                $motivos[] = 'Antivirus desactualizado, inactivo o ausente';
            }
            if (($n = $c->softwarePendienteActualizacion()) > 0) {
                $motivos[] = "{$n} programa(s) por actualizar";
            }
            if ($c->garantia_hasta !== null && $c->garantia_hasta->isPast() && $c->estado_operativo !== 'baja') {
                $motivos[] = 'Garantía vencida';
            }
            if ($motivos === []) {
                continue;
            }
            $ultimo = $c->mantenimientos->first();
            $hoja->fromArray([
                $c->laboratorio?->edificio?->nombre, $c->laboratorio?->nombre, $c->nombre_equipo, $c->numero_inventario,
                C::ESTADOS_OPERATIVOS[$c->estado_operativo] ?? $c->estado_operativo, implode('; ', $motivos),
                $ultimo ? $ultimo->fecha->toDateString() . ' (' . (C::TIPOS_MANTENIMIENTO[$ultimo->tipo] ?? $ultimo->tipo) . ')' : 'Sin registro',
            ], null, "A{$fila}", true);
            $fila++;
        }
        $this->estilizarEncabezado($hoja, count($encabezados));
    }

    private function etiquetaAntivirus(string $estado): string
    {
        return ['actualizado' => 'Actualizado', 'desactualizado' => 'Requiere atención', 'sin_informacion' => 'Sin información'][$estado] ?? $estado;
    }

    private function estilizarEncabezado(Worksheet $hoja, int $columnas): void
    {
        $ultima = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnas);
        $hoja->getStyle("A1:{$ultima}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $hoja->getStyle("A1:{$ultima}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0A7A3D');
        $hoja->freezePane('A2');
        $hoja->setAutoFilter("A1:{$ultima}1");
        for ($i = 1; $i <= $columnas; $i++) {
            $hoja->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
    }
}
