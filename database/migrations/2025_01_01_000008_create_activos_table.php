<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nombre', 150);
            $table->string('tipo', 20); // TipoActivo enum
            $table->unsignedTinyInteger('confidencialidad'); // 1-5
            $table->unsignedTinyInteger('integridad'); // 1-5
            $table->unsignedTinyInteger('disponibilidad'); // 1-5
            $table->unsignedTinyInteger('criticidad_calculada')->nullable();
            $table->foreignId('departamento_id')->constrained('departamentos')->restrictOnDelete();
            $table->foreignId('ubicacion_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('codigo_qr', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tipo', 'departamento_id']);
            $table->index('nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activos');
    }
};