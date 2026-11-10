<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hallazgos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auditoria_id')->constrained('auditorias')->cascadeOnDelete();
            $table->string('severidad', 10); // SeveridadHallazgo enum
            $table->text('descripcion');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hallazgos');
    }
};