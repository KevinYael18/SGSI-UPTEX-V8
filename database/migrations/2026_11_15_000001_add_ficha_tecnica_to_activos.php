<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ficha técnica dinámica de activos (ampliación del módulo de Activos).
 *
 * Solo agrega columnas OPCIONALES (NULL o con valor por defecto). Los activos,
 * ubicaciones, programas e instalaciones existentes quedan intactos:
 *  - activos.subtipo / caracteristicas / ubicacion_detalle: tipo reconocido
 *    (p. ej. «camara»), campos técnicos del tipo y atributos personalizados.
 *    CIA, criticidad y relaciones NO cambian.
 *  - ubicaciones.edificio_id / piso / tipo_espacio: estructura física ampliable
 *    (edificio → piso → área/laboratorio/aula/oficina).
 *  - software_catalogo.activo_id: vincula un activo de tipo software con su
 *    entrada del catálogo central (relación software ↔ equipos).
 *  - computadora_software.estado_instalacion: «instalado» / «desinstalado»
 *    para conservar el historial en lugar de borrar la instalación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activos', function (Blueprint $table) {
            if (!Schema::hasColumn('activos', 'subtipo')) {
                $table->string('subtipo', 40)->nullable()->after('tipo');
                $table->json('caracteristicas')->nullable()->after('subtipo');
                $table->string('ubicacion_detalle', 150)->nullable()->after('ubicacion_id');
                $table->index('subtipo');
            }
        });

        Schema::table('ubicaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('ubicaciones', 'edificio_id')) {
                $table->foreignId('edificio_id')->nullable()->after('departamento_id')->constrained('edificios')->nullOnDelete();
                $table->string('piso', 30)->nullable()->after('edificio');
                $table->string('tipo_espacio', 20)->nullable()->after('piso');
            }
        });

        Schema::table('software_catalogo', function (Blueprint $table) {
            if (!Schema::hasColumn('software_catalogo', 'activo_id')) {
                $table->foreignId('activo_id')->nullable()->unique()->after('id')->constrained('activos')->nullOnDelete();
            }
        });

        Schema::table('computadora_software', function (Blueprint $table) {
            if (!Schema::hasColumn('computadora_software', 'estado_instalacion')) {
                $table->string('estado_instalacion', 15)->default('instalado')->after('software_id');
            }
        });

        // Las ubicaciones creadas automáticamente para laboratorios se enlazan a
        // su edificio y se marcan como «laboratorio» (solo completa datos nuevos).
        foreach (DB::table('laboratorios')->whereNotNull('ubicacion_id')->get(['ubicacion_id', 'edificio_id']) as $lab) {
            DB::table('ubicaciones')->where('id', $lab->ubicacion_id)->whereNull('edificio_id')
                ->update(['edificio_id' => $lab->edificio_id, 'tipo_espacio' => 'laboratorio']);
        }
    }

    public function down(): void
    {
        Schema::table('computadora_software', function (Blueprint $table) {
            if (Schema::hasColumn('computadora_software', 'estado_instalacion')) {
                $table->dropColumn('estado_instalacion');
            }
        });
        Schema::table('software_catalogo', function (Blueprint $table) {
            if (Schema::hasColumn('software_catalogo', 'activo_id')) {
                $table->dropConstrainedForeignId('activo_id');
            }
        });
        Schema::table('ubicaciones', function (Blueprint $table) {
            if (Schema::hasColumn('ubicaciones', 'edificio_id')) {
                $table->dropConstrainedForeignId('edificio_id');
                $table->dropColumn(['piso', 'tipo_espacio']);
            }
        });
        Schema::table('activos', function (Blueprint $table) {
            if (Schema::hasColumn('activos', 'subtipo')) {
                $table->dropIndex(['subtipo']);
                $table->dropColumn(['subtipo', 'caracteristicas', 'ubicacion_detalle']);
            }
        });
    }
};
