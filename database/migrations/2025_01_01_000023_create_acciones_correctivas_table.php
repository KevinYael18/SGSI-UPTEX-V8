<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('acciones_correctivas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('no_conformidad_id')->constrained('no_conformidades')->cascadeOnDelete();
            $table->foreignId('responsable_id')->constrained('usuarios')->restrictOnDelete();
            $table->date('fecha_compromiso');
            $table->string('estado', 15)->default('abierta'); // EstadoAuditoriaGenerico enum
            $table->timestamps();

            $table->index(['estado', 'fecha_compromiso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acciones_correctivas');
    }
};