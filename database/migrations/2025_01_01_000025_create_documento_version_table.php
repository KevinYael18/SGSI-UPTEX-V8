<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documento_version', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('documentos')->cascadeOnDelete();
            $table->unsignedInteger('numero_version');
            $table->string('path_storage', 255);
            $table->foreignId('aprobado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->date('fecha_expiracion')->nullable();
            $table->timestamps();

            $table->unique(['documento_id', 'numero_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_version');
    }
};