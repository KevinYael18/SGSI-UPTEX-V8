<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolesPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $modulos = ['usuarios', 'activos', 'riesgos', 'incidentes', 'auditorias', 'documentos', 'cumplimiento', 'dashboard'];
        $acciones = ['ver', 'crear', 'editar', 'eliminar'];

        $permisos = [];
        foreach ($modulos as $modulo) {
            foreach ($acciones as $accion) {
                $permisos[] = ['clave' => "{$modulo}.{$accion}", 'modulo' => $modulo];
            }
        }
        // Permisos finos adicionales usados por Policies específicas
        $permisos[] = ['clave' => 'activos.editar_propio', 'modulo' => 'activos'];
        $permisos[] = ['clave' => 'incidentes.gestionar', 'modulo' => 'incidentes'];
        $permisos[] = ['clave' => 'usuarios.suspender', 'modulo' => 'usuarios'];

        foreach ($permisos as $p) {
            Permiso::query()->firstOrCreate(['clave' => $p['clave']], $p);
        }

        $roles = [
            'Administrador' => '*', // Gate::before ya le da acceso total
            'Auditor' => ['auditorias.*', 'documentos.ver', 'documentos.crear'],
            'Responsable SGSI' => ['riesgos.*', 'cumplimiento.*', 'dashboard.ver', 'activos.ver'],
            'Responsable de Activo' => ['activos.ver', 'activos.editar_propio', 'incidentes.ver', 'incidentes.crear'],
            'Director' => ['dashboard.ver', '*.ver'],
            'Consulta' => ['*.ver'],
        ];

        foreach ($roles as $nombre => $patronesPermisos) {
            $rol = Rol::query()->firstOrCreate(['nombre' => $nombre]);

            if ($patronesPermisos === '*') {
                $rol->permisos()->sync(Permiso::query()->pluck('id'));
                continue;
            }

            $idsAsignar = collect();
            foreach ((array) $patronesPermisos as $patron) {
                $query = Permiso::query();
                if (str_ends_with($patron, '.*')) {
                    $modulo = str_replace('.*', '', $patron);
                    $query->where('modulo', $modulo);
                } elseif (str_starts_with($patron, '*.')) {
                    $accion = str_replace('*.', '', $patron);
                    $query->where('clave', 'like', "%.{$accion}");
                } else {
                    $query->where('clave', $patron);
                }
                $idsAsignar = $idsAsignar->merge($query->pluck('id'));
            }
            $rol->permisos()->sync($idsAsignar->unique());
        }
    }
}