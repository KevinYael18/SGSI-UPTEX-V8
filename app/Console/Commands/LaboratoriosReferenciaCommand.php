<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Departamento;
use App\Models\Edificio;
use App\Models\Laboratorio;
use App\Services\InventarioService;
use Illuminate\Console\Command;

/**
 * Registra los laboratorios de REFERENCIA indicados por la institución:
 *   Laboratorio 1 y 2 → «Edificio B»
 *   Laboratorio 4, 5 y 6 → «Rectoría/Biblioteca»
 * Los nombres son provisionales y se pueden editar después desde Inventario TI.
 * Es idempotente: no duplica edificios ni laboratorios ya existentes y no se
 * ejecuta automáticamente (solo cuando el administrador lo decide).
 *
 *   php artisan sgsi:inventario:laboratorios-referencia --departamento=ID
 */
class LaboratoriosReferenciaCommand extends Command
{
    protected $signature = 'sgsi:inventario:laboratorios-referencia {--departamento= : ID del departamento responsable de los laboratorios}';

    protected $description = 'Registra los laboratorios de referencia (Edificio B: 1 y 2; Rectoría/Biblioteca: 4, 5 y 6). Nombres editables después.';

    private const REFERENCIA = [
        'Edificio B' => ['Laboratorio 1', 'Laboratorio 2'],
        'Rectoría/Biblioteca' => ['Laboratorio 4', 'Laboratorio 5', 'Laboratorio 6'],
    ];

    public function handle(InventarioService $inventario): int
    {
        $departamento = Departamento::query()->find($this->option('departamento'));
        if ($departamento === null) {
            $this->error('Indica un departamento existente con --departamento=ID. Disponibles:');
            $this->table(['ID', 'Clave', 'Nombre'], Departamento::query()->orderBy('nombre')->get(['id', 'clave', 'nombre'])->toArray());

            return self::FAILURE;
        }

        foreach (self::REFERENCIA as $nombreEdificio => $laboratorios) {
            $edificio = Edificio::query()->firstOrCreate(['nombre' => $nombreEdificio], ['descripcion' => 'Nombre de referencia; puede ajustarse.']);
            foreach ($laboratorios as $nombreLab) {
                if (Laboratorio::query()->where('edificio_id', $edificio->id)->where('nombre', $nombreLab)->exists()) {
                    $this->line("· Ya existe: {$nombreLab} ({$nombreEdificio})");
                    continue;
                }
                $inventario->crearLaboratorio(['edificio_id' => $edificio->id, 'departamento_id' => $departamento->id, 'nombre' => $nombreLab]);
                $this->info("✓ Creado: {$nombreLab} ({$nombreEdificio})");
            }
        }

        return self::SUCCESS;
    }
}
