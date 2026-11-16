<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riesgo residual y seguimiento (ISO/IEC 27001:2022 cláusula 6.1.3 / 8.3).
 * Columnas NUEVAS y opcionales: los riesgos existentes quedan intactos
 * (residual en NULL = aún no evaluado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riesgos', function (Blueprint $table) {
            if (!Schema::hasColumn('riesgos', 'probabilidad_residual')) {
                $table->unsignedTinyInteger('probabilidad_residual')->nullable()->after('tipo_tratamiento');
                $table->unsignedTinyInteger('impacto_residual')->nullable()->after('probabilidad_residual');
                $table->string('nivel_residual', 10)->nullable()->after('impacto_residual');
                $table->date('fecha_revision')->nullable()->after('nivel_residual');
            }
        });
    }

    public function down(): void
    {
        Schema::table('riesgos', function (Blueprint $table) {
            $table->dropColumn(array_filter(
                ['probabilidad_residual', 'impacto_residual', 'nivel_residual', 'fecha_revision'],
                fn ($c) => Schema::hasColumn('riesgos', $c)
            ));
        });
    }
};
