<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El diseño original de Documentos (Fase 2) contemplaba 4 estados
 * (borrador/en_revision/aprobado/obsoleto). El workflow completo pedido
 * ahora agrega "publicado" entre aprobado y obsoleto: un documento puede
 * estar Aprobado (listo, sin publicar aún) y luego Publicado (visible
 * institucionalmente). Se deja migración incremental en vez de reescribir
 * la original: así el historial de cambios de esquema queda trazable,
 * igual que se exige para el resto del sistema (ISO 7.5).
 */
return new class extends Migration {
    public function up(): void
    {
        // La columna ya es VARCHAR/ENUM corto (estado, 15 chars) definida en
        // 2025_01_01_000024_create_documentos_table.php; el nuevo valor
        // 'publicado' cabe sin ALTER de tipo. Se documenta aquí el cambio
        // de dominio permitido a nivel de aplicación (EstadoDocumento enum),
        // y se agrega el campo de auditoría de publicación.
        Schema::table('documentos', function (Blueprint $table) {
            $table->foreignId('publicado_por')->nullable()->after('version_actual')
                ->constrained('usuarios')->nullOnDelete();
            $table->dateTime('publicado_en')->nullable()->after('publicado_por');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('publicado_por');
            $table->dropColumn('publicado_en');
        });
    }
};