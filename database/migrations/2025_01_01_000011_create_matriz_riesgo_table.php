<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('matriz_riesgo', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('probabilidad'); // 1-5
            $table->unsignedTinyInteger('impacto'); // 1-5
            $table->string('nivel_resultante', 10); // NivelRiesgo enum
            $table->timestamps();

            $table->unique(['probabilidad', 'impacto']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matriz_riesgo');
    }
};