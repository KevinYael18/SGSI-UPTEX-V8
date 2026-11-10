<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('planes_tratamiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('riesgo_id')->constrained('riesgos')->cascadeOnDelete();
            $table->text('descripcion');
            $table->foreignId('responsable_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->date('fecha_objetivo')->nullable();
            $table->string('estado', 15)->default('abierta'); // EstadoAuditoriaGenerico enum
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes_tratamiento');
    }
};