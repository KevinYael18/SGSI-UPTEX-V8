<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('entidad', 60);
            $table->unsignedBigInteger('entidad_id');
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('accion', 10); // create|update|delete|restore
            $table->json('cambios')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Mejora #9 Ronda 2: índice compuesto para "historial de esta entidad".
            $table->index(['entidad', 'entidad_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};