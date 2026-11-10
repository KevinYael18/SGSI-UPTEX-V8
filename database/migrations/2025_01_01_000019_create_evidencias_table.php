<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evidencias', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Relación polimórfica: reutilizable por incidentes, hallazgos, auditorías.
            $table->string('evidenciable_type', 60);
            $table->unsignedBigInteger('evidenciable_id');
            $table->foreignId('subido_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('path_storage', 255);
            $table->string('mime_type', 100);
            $table->char('hash_sha256', 64);
            $table->date('fecha_expiracion')->nullable();
            $table->timestamps();

            $table->index(['evidenciable_type', 'evidenciable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidencias');
    }
};