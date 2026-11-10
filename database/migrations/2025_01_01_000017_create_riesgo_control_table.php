<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('riesgo_control', function (Blueprint $table) {
            $table->foreignId('riesgo_id')->constrained('riesgos')->cascadeOnDelete();
            $table->foreignId('control_id')->constrained('controles_iso')->cascadeOnDelete();
            $table->primary(['riesgo_id', 'control_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riesgo_control');
    }
};