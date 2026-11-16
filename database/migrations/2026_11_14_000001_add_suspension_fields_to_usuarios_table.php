<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad de la suspensión de cuentas (solo columnas NUEVAS y opcionales:
 * no altera ni borra datos existentes). El estado sigue viviendo en `estado`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            if (!Schema::hasColumn('usuarios', 'suspendido_at')) {
                $table->dateTime('suspendido_at')->nullable()->after('estado');
            }
            if (!Schema::hasColumn('usuarios', 'suspendido_por')) {
                $table->foreignId('suspendido_por')->nullable()->after('suspendido_at')
                    ->constrained('usuarios')->nullOnDelete();
            }
            if (!Schema::hasColumn('usuarios', 'motivo_suspension')) {
                $table->string('motivo_suspension', 255)->nullable()->after('suspendido_por');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            if (Schema::hasColumn('usuarios', 'suspendido_por')) {
                $table->dropConstrainedForeignId('suspendido_por');
            }
            $table->dropColumn(array_filter(
                ['suspendido_at', 'motivo_suspension'],
                fn ($c) => Schema::hasColumn('usuarios', $c)
            ));
        });
    }
};
