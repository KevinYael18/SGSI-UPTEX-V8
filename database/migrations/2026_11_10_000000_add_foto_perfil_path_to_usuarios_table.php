<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo «Mi perfil»: ruta (relativa al disco `public`) de la fotografía
 * de perfil. Solo se guarda la RUTA; el archivo vive en
 * storage/app/public/fotos-perfil (volumen Docker persistente).
 * Migración aditiva: no toca ninguna otra columna ni registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('usuarios', 'foto_perfil_path')) {
            return;
        }

        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('foto_perfil_path', 255)->nullable()->after('ultimo_acceso');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('usuarios', 'foto_perfil_path')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->dropColumn('foto_perfil_path');
            });
        }
    }
};
