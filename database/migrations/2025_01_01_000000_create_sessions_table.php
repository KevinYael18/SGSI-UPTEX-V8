<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requerida por SESSION_DRIVER=database (decisión de arquitectura de 2
 * contenedores, sin Redis — ver docs/02-Decision-Arquitectura-2-Contenedores.md).
 * Se versiona explícitamente en vez de depender de que el scaffold de
 * `composer create-project laravel/laravel` la incluya o no por defecto,
 * para que el esquema final sea 100% predecible.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};