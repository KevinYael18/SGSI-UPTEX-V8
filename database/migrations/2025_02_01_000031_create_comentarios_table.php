<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('comentarios', function (Blueprint $table) {
            $table->id();
            // Polimórfico: reutilizable por Incidentes, Riesgos, Auditorías, Acciones Correctivas.
            $table->string('comentable_type', 60);
            $table->unsignedBigInteger('comentable_id');
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->text('contenido');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['comentable_type', 'comentable_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comentarios');
    }
};