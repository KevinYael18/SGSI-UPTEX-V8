<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faltaba registrar quién SUBIÓ cada versión (solo se registraba quién la
 * aprueba). Necesario para notificar al autor cuando su documento se
 * aprueba/publica, y para el timeline de actividad del documento.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('documento_version', function (Blueprint $table) {
            $table->foreignId('creado_por')->nullable()->after('documento_id')
                ->constrained('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documento_version', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creado_por');
        });
    }
};