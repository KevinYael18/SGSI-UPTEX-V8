<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('no_conformidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hallazgo_id')->constrained('hallazgos')->cascadeOnDelete();
            $table->string('estado', 15)->default('abierta'); // EstadoAuditoriaGenerico enum
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('no_conformidades');
    }
};