<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('riesgos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();
            $table->foreignId('amenaza_id')->constrained('amenazas')->restrictOnDelete();
            $table->foreignId('vulnerabilidad_id')->constrained('vulnerabilidades')->restrictOnDelete();
            $table->unsignedTinyInteger('probabilidad');
            $table->unsignedTinyInteger('impacto');
            $table->string('nivel_calculado', 10)->nullable(); // NivelRiesgo, resuelto vía matriz_riesgo
            $table->string('tipo_tratamiento', 15)->nullable(); // TipoTratamientoRiesgo enum
            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo_id', 'nivel_calculado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riesgos');
    }
};