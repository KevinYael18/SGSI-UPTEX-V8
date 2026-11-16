<?php

declare(strict_types=1);

use App\Models\Activo;
use App\Models\Amenaza;
use App\Models\ControlIso;
use App\Models\Departamento;
use App\Models\Incidente;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Vulnerabilidad;
use App\Services\AI\SGSIIntelligenceService;
use Database\Seeders\MatrizRiesgoSeeder;
use Illuminate\Http\UploadedFile;

/*
 * Prueba de humo CONTRA GEMINI REAL (consume una pequeña cantidad de tokens).
 * Desactivada por defecto: solo corre con GEMINI_EN_VIVO=1 y una API key
 * válida. Usa la base de pruebas con datos sintéticos (nunca la base real).
 *
 *   GEMINI_EN_VIVO=1 php artisan test --filter=GeminiEnVivo
 */
beforeEach(function () {
    if (!env('GEMINI_EN_VIVO') || blank(env('GEMINI_API_KEY'))) {
        $this->markTestSkipped('Prueba en vivo desactivada (defina GEMINI_EN_VIVO=1 y GEMINI_API_KEY).');
    }
    config(['sgsi.ia.gemini.key' => env('GEMINI_API_KEY')]);

    $this->admin = Usuario::factory()->create(['rol_id' => Rol::factory()->create(['nombre' => 'Administrador'])->id]);
    $this->activo = Activo::factory()->create([
        'departamento_id' => Departamento::factory(), 'nombre' => 'Servidor del sistema de control escolar', 'tipo' => 'hardware',
        'confidencialidad' => 5, 'integridad' => 5, 'disponibilidad' => 4,
    ]);
    foreach ([['A.8.13', 'tecnologico', 'Respaldo de la información'], ['A.8.7', 'tecnologico', 'Protección contra malware'],
        ['A.5.24', 'organizacional', 'Planificación y preparación de la gestión de incidentes de seguridad de la información'],
        ['A.8.8', 'tecnologico', 'Gestión de vulnerabilidades técnicas'], ['A.5.15', 'organizacional', 'Control de acceso']] as [$c, $cat, $n]) {
        ControlIso::query()->create(['codigo' => $c, 'categoria' => $cat, 'nombre' => $n]);
    }
});

// Diagnóstico: cada intento contra el proveedor (modelo, resultado, latencia, tokens).
afterEach(function () {
    if (!env('GEMINI_EN_VIVO')) {
        return;
    }
    foreach (\App\Models\IaRegistroUso::query()->orderBy('id')->get() as $u) {
        fwrite(STDERR, "\n[uso] {$u->operacion} {$u->modelo} exito=" . (int) $u->exito . " error={$u->codigo_error} {$u->latencia_ms}ms tokens={$u->tokens_entrada}/{$u->tokens_salida}");
    }
});

it('responde en vivo una pregunta de ISO 27001 sin salir de su identidad', function () {
    $r = $this->actingAs($this->admin)->postJson('/api/v1/ia/chat', ['mensaje' => '¿Qué controles del Anexo A ayudan a protegerse contra ransomware?'])
        ->assertOk()->json('data');

    expect($r['respuesta']['estado'])->toBe('ok')->and(mb_strlen($r['respuesta']['contenido']))->toBeGreaterThan(80);
    fwrite(STDERR, "\n--- CHAT ISO (en vivo) ---\n" . mb_substr($r['respuesta']['contenido'], 0, 700) . "\n");
});

it('rechaza en vivo lo ambiguo fuera de dominio con el clasificador ligero', function () {
    $r = $this->actingAs($this->admin)->postJson('/api/v1/ia/chat', ['mensaje' => '¿Cuál es la mejor marca de tenis para correr un maratón?'])
        ->assertOk()->json('data.respuesta');

    expect($r['estado'])->toBe('rechazado')->and($r['contenido'])->toBe(SGSIIntelligenceService::RESPUESTA_FUERA_DE_DOMINIO);
});

it('analiza en vivo un riesgo con la metodología del sistema', function () {
    $this->seed(MatrizRiesgoSeeder::class);
    $r = $this->actingAs($this->admin)->postJson('/api/v1/ia/sugerir-riesgo', [
        'activo_id' => $this->activo->id,
        'amenaza_id' => Amenaza::factory()->create(['nombre' => 'Ransomware', 'categoria' => 'humana_intencional'])->id,
        'vulnerabilidad_id' => Vulnerabilidad::factory()->create(['nombre' => 'Ausencia de respaldo periódico'])->id,
        'descripcion' => 'El servidor está expuesto a Internet y los respaldos se guardan en el mismo equipo.',
    ])->assertOk()->json('data');

    expect($r['probabilidad_sugerida'])->toBeBetween(1, 5)->and($r['impacto_sugerido'])->toBeBetween(1, 5)
        ->and($r['nivel_sugerido'])->toBeIn(['bajo', 'medio', 'alto', 'critico']);
    fwrite(STDERR, "\n--- RIESGO (en vivo) --- modelo {$r['modelo']} · P{$r['probabilidad_sugerida']} I{$r['impacto_sugerido']} → {$r['nivel_sugerido']} · tratamiento "
        . ($r['tratamiento_sugerido'] ?? 'n/d') . ' · controles ' . implode(', ', array_column($r['controles_sugeridos'], 'codigo')) . "\n");
});

it('analiza en vivo un incidente sin confirmarlo sin evidencias', function () {
    $incidente = Incidente::factory()->create([
        'activo_id' => $this->activo->id, 'reportado_por' => $this->admin->id, 'estado' => 'abierto',
        'titulo' => 'Correo sospechoso con adjunto', 'descripcion' => 'Un docente reporta que abrió un adjunto de un correo que parecía de rectoría; desde entonces su equipo está lento.',
    ]);

    $r = $this->actingAs($this->admin)->postJson("/api/v1/ia/analizar/incidentes/{$incidente->uuid}")->assertOk()->json('data');

    expect($r['nivel_certeza'])->not->toBe('confirmado')->and($r['acciones_contencion'])->not->toBeEmpty();
    fwrite(STDERR, "\n--- INCIDENTE (en vivo) --- {$r['categoria_sugerida']} · {$r['nivel_certeza']} · contención: " . ($r['acciones_contencion'][0] ?? '') . "\n");
});

it('indexa y cita en vivo un documento de la base de conocimiento', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/ia/conocimiento', [
        'archivo' => UploadedFile::fake()->createWithContent('politica.md', "Política de respaldos UPTex\n\nLos respaldos del servidor de control escolar se realizan diariamente a las 23:00 y se conservan 30 días en un almacenamiento externo cifrado. El responsable es el área de Infraestructura de TI."),
        'titulo' => 'Política de respaldos UPTex', 'categoria' => 'politica',
    ])->assertCreated()->assertJsonPath('data.estado', 'listo');

    $r = $this->actingAs($this->admin)->postJson('/api/v1/ia/chat', ['mensaje' => '¿Cuántos días se conservan los respaldos según la política de respaldos?'])
        ->assertOk()->json('data.respuesta');

    expect(collect($r['fuentes'])->pluck('titulo'))->toContain('Política de respaldos UPTex')
        ->and($r['contenido'])->toContain('30');
    fwrite(STDERR, "\n--- RAG (en vivo) ---\n" . mb_substr($r['contenido'], 0, 400) . "\n");
});
