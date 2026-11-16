<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventario tecnológico (ISO/IEC 27001:2022 A.5.9, A.7.8, A.7.13, A.8.1,
 * A.8.7, A.8.9, A.8.19).
 *
 * Diseño: cada computadora ES un activo existente (tabla `activos`, tipo
 * hardware) con una ficha técnica 1:1 en `computadoras`. Así hereda sin
 * duplicar: riesgos, incidentes, controles ISO (activo_control), QR,
 * responsable, departamento y bitácora del módulo de Activos.
 *
 * Solo crea tablas NUEVAS: no altera ni borra datos existentes. No inserta
 * edificios, laboratorios, equipos ni software (los registra la institución).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edificios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->string('clave', 20)->nullable()->unique();
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('laboratorios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edificio_id')->constrained('edificios')->restrictOnDelete();
            // Departamento dueño: se usa como departamento por defecto de los activos
            // de sus equipos (la tabla activos lo exige).
            $table->foreignId('departamento_id')->constrained('departamentos')->restrictOnDelete();
            // Ubicación del catálogo existente, para que los activos del laboratorio
            // se vean ubicados también en el módulo de Activos.
            $table->foreignId('ubicacion_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('nombre', 120);
            $table->string('clave', 30)->nullable();
            $table->unsignedSmallInteger('capacidad')->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();

            $table->unique(['edificio_id', 'nombre']);
        });

        Schema::create('software_catalogo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('fabricante', 120)->nullable();
            $table->string('categoria', 30); // InventarioCatalogos::CATEGORIAS_SOFTWARE
            $table->string('tipo_licencia', 20)->default('desconocida');
            // Nunca se presume ilegal: sin información queda "pendiente_verificacion".
            $table->string('estado_autorizacion', 25)->default('pendiente_verificacion');
            // Versión mínima aprobada: instalaciones con versión menor = desactualizadas.
            $table->string('version_vigente', 60)->nullable();
            $table->boolean('es_antivirus')->default(false);
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->unique(['nombre', 'fabricante']);
            $table->index(['categoria', 'estado_autorizacion']);
        });

        Schema::create('computadoras', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('activo_id')->unique()->constrained('activos')->cascadeOnDelete();
            $table->foreignId('laboratorio_id')->nullable()->constrained('laboratorios')->nullOnDelete();

            // Identificación
            $table->string('nombre_equipo', 100);
            $table->string('numero_inventario', 60)->nullable()->unique();
            $table->string('numero_serie', 100)->nullable()->unique();
            $table->string('direccion_mac', 17)->nullable()->unique();
            $table->string('direccion_ip', 45)->nullable();
            $table->string('ubicacion_fisica', 120)->nullable(); // p. ej. "Fila 2, lugar 5"
            $table->string('estado_operativo', 20)->default('operativo');

            // Hardware
            $table->string('fabricante', 80)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('procesador', 150)->nullable();
            $table->unsignedSmallInteger('ram_gb')->nullable();
            $table->unsignedInteger('almacenamiento_gb')->nullable();
            $table->string('tipo_almacenamiento', 15)->nullable();
            $table->string('tarjeta_grafica', 150)->nullable();
            $table->date('fecha_adquisicion')->nullable();
            $table->date('garantia_hasta')->nullable();

            // Sistema operativo
            $table->string('so_nombre', 80)->nullable();
            $table->string('so_edicion', 80)->nullable();
            $table->string('so_version', 60)->nullable();
            $table->string('so_arquitectura', 10)->nullable();
            $table->string('so_activacion', 20)->default('desconocido');
            $table->date('so_ultima_actualizacion')->nullable();
            $table->string('so_soporte', 20)->default('desconocido');

            // Antivirus
            $table->string('antivirus_nombre', 100)->nullable();
            $table->string('antivirus_fabricante', 100)->nullable();
            $table->string('antivirus_version', 60)->nullable();
            $table->string('antivirus_instalacion', 20)->default('desconocido');
            $table->string('antivirus_proteccion', 20)->default('desconocido');
            $table->date('antivirus_ultima_actualizacion')->nullable();

            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['laboratorio_id', 'estado_operativo']);
            $table->index('so_nombre');
        });

        Schema::create('computadora_software', function (Blueprint $table) {
            $table->id();
            $table->foreignId('computadora_id')->constrained('computadoras')->cascadeOnDelete();
            $table->foreignId('software_id')->constrained('software_catalogo')->restrictOnDelete();
            $table->string('version', 60)->nullable();
            $table->date('fecha_instalacion')->nullable();
            $table->string('estado_licencia', 30)->default('pendiente_verificacion');
            $table->boolean('requiere_actualizacion')->default(false);
            $table->string('observaciones', 255)->nullable();
            $table->timestamps();

            $table->unique(['computadora_id', 'software_id']);
            $table->index('software_id');
        });

        Schema::create('mantenimientos_equipo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('computadora_id')->constrained('computadoras')->cascadeOnDelete();
            $table->string('tipo', 25);
            $table->date('fecha');
            $table->text('descripcion');
            $table->string('realizado_por', 150)->nullable();
            $table->string('estado_resultante', 20)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index(['computadora_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mantenimientos_equipo');
        Schema::dropIfExists('computadora_software');
        Schema::dropIfExists('computadoras');
        Schema::dropIfExists('software_catalogo');
        Schema::dropIfExists('laboratorios');
        Schema::dropIfExists('edificios');
    }
};
