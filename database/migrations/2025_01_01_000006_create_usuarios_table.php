<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nombre', 150);
            $table->string('email', 150)->unique();
            $table->string('password_hash', 255);
            $table->foreignId('rol_id')->constrained('roles')->restrictOnDelete();
            $table->string('estado', 20)->default('activo'); // EstadoUsuario enum
            $table->unsignedTinyInteger('intentos_fallidos')->default(0);
            $table->dateTime('bloqueado_hasta')->nullable();
            $table->dateTime('ultimo_acceso')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};