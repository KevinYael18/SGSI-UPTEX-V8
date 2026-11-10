<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('controles_iso', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique(); // ej. A.5.1, A.8.24
            $table->string('categoria', 20); // CategoriaControlIso enum
            $table->foreignId('clausula_id')->nullable()->constrained('clausulas_iso')->nullOnDelete();
            $table->string('nombre', 200);
            $table->string('estado_implementacion', 20)->default('no_implementado'); // EstadoControlIso enum
            $table->timestamps();

            $table->index('categoria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controles_iso');
    }
};