<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activo_control', function (Blueprint $table) {
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();
            $table->foreignId('control_id')->constrained('controles_iso')->cascadeOnDelete();
            $table->primary(['activo_id', 'control_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activo_control');
    }
};