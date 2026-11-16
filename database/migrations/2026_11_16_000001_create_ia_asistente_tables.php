<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SGSI Intelligence Assistant: historial de conversaciones, análisis por
 * módulo (propuestas revisables), base de conocimiento (RAG) y registro de
 * consumo. Solo crea tablas NUEVAS: no altera ni borra datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Conversaciones privadas: cada una pertenece a UN usuario.
        Schema::create('ia_conversaciones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('titulo', 120);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['usuario_id', 'updated_at']);
        });

        Schema::create('ia_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversacion_id')->constrained('ia_conversaciones')->cascadeOnDelete();
            $table->string('rol', 10); // usuario | asistente
            $table->text('contenido');
            $table->string('categoria', 30)->nullable(); // categoría del clasificador
            // ok | rechazado (fuera de dominio) | bloqueado (manipulación) | error
            $table->string('estado', 12)->default('ok');
            $table->json('fuentes')->nullable();   // referencias documentales usadas
            $table->json('metadatos')->nullable(); // contexto consultado, modelo, etc.
            $table->unsignedInteger('tokens_entrada')->default(0);
            $table->unsignedInteger('tokens_salida')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index(['conversacion_id', 'id']);
        });

        // Cada análisis contextual de un módulo es una PROPUESTA que el
        // responsable acepta, rechaza o aplica parcialmente.
        Schema::create('ia_analisis', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('tipo', 20); // activo | riesgo | incidente | auditoria | documento | dashboard
            $table->string('entidad_tipo', 40)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->json('entrada')->nullable();   // resumen de lo enviado (sin secretos)
            $table->json('resultado');
            // pendiente | aceptada | rechazada | parcial
            $table->string('estado_propuesta', 12)->default('pendiente');
            $table->foreignId('decidido_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('decidido_en')->nullable();
            $table->string('proveedor', 20)->default('gemini');
            $table->string('modelo', 60)->nullable();
            $table->unsignedInteger('tokens_entrada')->default(0);
            $table->unsignedInteger('tokens_salida')->default(0);
            $table->timestamps();
            $table->index(['tipo', 'entidad_tipo', 'entidad_id']);
            $table->index(['usuario_id', 'created_at']);
        });

        Schema::create('ia_documentos_conocimiento', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('titulo', 200);
            // institucional | procedimiento | politica | tecnica | normativa
            $table->string('categoria', 20);
            $table->string('descripcion', 500)->nullable();
            $table->string('nombre_archivo', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->char('hash_sha256', 64)->nullable();
            $table->string('estado', 12)->default('procesando'); // procesando | listo | error
            $table->string('error', 255)->nullable();
            $table->unsignedInteger('total_fragmentos')->default(0);
            $table->unsignedInteger('fragmentos_sospechosos')->default(0);
            $table->boolean('activo')->default(true);
            $table->foreignId('subido_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ia_fragmentos_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('ia_documentos_conocimiento')->cascadeOnDelete();
            $table->unsignedInteger('orden');
            $table->text('contenido');
            // Vector float32 empaquetado en base64 (más compacto que JSON).
            $table->mediumText('embedding')->nullable();
            $table->string('modelo_embedding', 60)->nullable();
            // Contiene texto que parece una instrucción dirigida a la IA: no se
            // usa como contexto (protección de la recuperación documental).
            $table->boolean('sospechoso')->default(false);
            $table->timestamps();
            $table->index(['documento_id', 'orden']);
        });

        Schema::create('ia_registros_uso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            // chat | clasificacion | analisis_<tipo> | embedding | extraccion
            $table->string('operacion', 30);
            $table->string('proveedor', 20)->default('gemini');
            $table->string('modelo', 60)->nullable();
            $table->string('categoria', 30)->nullable();
            $table->unsignedInteger('tokens_entrada')->default(0);
            $table->unsignedInteger('tokens_salida')->default(0);
            $table->boolean('exito')->default(true);
            $table->string('codigo_error', 40)->nullable();
            $table->unsignedInteger('latencia_ms')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['usuario_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_registros_uso');
        Schema::dropIfExists('ia_fragmentos_documento');
        Schema::dropIfExists('ia_documentos_conocimiento');
        Schema::dropIfExists('ia_analisis');
        Schema::dropIfExists('ia_mensajes');
        Schema::dropIfExists('ia_conversaciones');
    }
};
