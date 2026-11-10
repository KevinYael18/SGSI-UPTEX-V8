<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('health_check_log', function (Blueprint $table) {
            $table->id();
            $table->string('estado_bd', 10);
            $table->string('estado_cola', 10);
            $table->string('estado_storage', 10);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_check_log');
    }
};