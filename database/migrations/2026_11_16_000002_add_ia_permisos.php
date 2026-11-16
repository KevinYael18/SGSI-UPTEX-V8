<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos del SGSI Intelligence Assistant. Se insertan por migración (y no
 * solo en el seeder) para que las bases ya en uso los reciban al actualizar.
 * Idempotente: no duplica permisos ni asignaciones existentes.
 *
 *  - ia.usar          → usar el asistente (chat y análisis de los módulos que ya puede ver)
 *  - ia.conocimiento  → administrar la base de conocimiento (RAG)
 *  - ia.administrar   → consultar el consumo y la actividad del asistente
 */
return new class extends Migration
{
    private const ASIGNACION = [
        'ia.usar' => ['Administrador', 'Auditor', 'Responsable SGSI', 'Responsable de Activo', 'Director', 'Consulta'],
        'ia.conocimiento' => ['Administrador', 'Responsable SGSI'],
        'ia.administrar' => ['Administrador'],
    ];

    public function up(): void
    {
        foreach (self::ASIGNACION as $clave => $roles) {
            $permisoId = DB::table('permisos')->where('clave', $clave)->value('id')
                ?? DB::table('permisos')->insertGetId([
                    'clave' => $clave, 'modulo' => 'ia', 'created_at' => now(), 'updated_at' => now(),
                ]);

            $rolIds = DB::table('roles')->whereIn('nombre', $roles)->pluck('id');
            foreach ($rolIds as $rolId) {
                DB::table('role_permission')->insertOrIgnore(['rol_id' => $rolId, 'permiso_id' => $permisoId]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permisos')->whereIn('clave', array_keys(self::ASIGNACION))->pluck('id');
        DB::table('role_permission')->whereIn('permiso_id', $ids)->delete();
        DB::table('permisos')->whereIn('id', $ids)->delete();
    }
};
