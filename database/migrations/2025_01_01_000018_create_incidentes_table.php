<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('incidentes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('activo_id')->nullable()->constrained('activos')->nullOnDelete();
            $table->foreignId('reportado_por')->constrained('usuarios')->restrictOnDelete();
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->string('estado', 15)->default('abierto'); // EstadoIncidente enum
            $table->dateTime('fecha_deteccion');
            $table->timestamps();
            $table->softDeletes();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidentes');
    }
};