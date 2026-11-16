<?php

declare(strict_types=1);

use App\Models\Activo;
use App\Models\Amenaza;
use App\Models\ControlIso;
use App\Models\Departamento;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Vulnerabilidad;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const URL_GEMINI = 'generativelanguage.googleapis.com/*';

function respuestaGemini(string $texto): array
{
    return ['candidates' => [['content' => ['parts' => [['text' => $texto]]]]]];
}

beforeEach(function () {
    config(['sgsi.ia.gemini.key' => 'clave-de-prueba', 'sgsi.ia.gemini.model' => 'gemini-3.5-flash']);

    $this->admin = Usuario::factory()->create(['rol_id' => Rol::factory()->create(['nombre' => 'Administrador'])->id]);
    $this->activo = Activo::factory()->create(['departamento_id' => Departamento::factory()]);
    $this->amenaza = Amenaza::factory()->create();
    $this->vulnerabilidad = Vulnerabilidad::factory()->create();
    $this->control = ControlIso::query()->create([
        'codigo' => 'A.8.13', 'categoria' => 'tecnologico', 'nombre' => 'Respaldo de la información',
    ]);

    $this->datos = [
        'activo_id' => $this->activo->id,
        'amenaza_id' => $this->amenaza->id,
        'vulnerabilidad_id' => $this->vulnerabilidad->id,
        'descripcion' => 'Servidor sin respaldo externo',
    ];
});

it('sugiere controles, probabilidad e impacto y descarta códigos que no existen en el catálogo', function () {
    $json = json_encode([
        'controles_sugeridos' => [
            ['codigo' => 'A.8.13', 'nombre' => 'Backup', 'justificacion' => 'Mitiga la pérdida de datos.'],
            ['codigo' => 'A.9.99', 'nombre' => 'Inventado', 'justificacion' => 'No existe.'],
        ],
        'probabilidad_sugerida' => 7,
        'impacto_sugerido' => '3',
        'justificacion_nivel' => 'Riesgo moderado.',
    ]);
    Http::fake([URL_GEMINI => Http::response(respuestaGemini("```json\n{$json}\n```"))]);

    $respuesta = $this->actingAs($this->admin)->postJson('/api/v1/ia/sugerir-riesgo', $this->datos);

    $respuesta->assertOk()
        ->assertJsonPath('data.probabilidad_sugerida', 5)
        ->assertJsonPath('data.impacto_sugerido', 3)
        ->assertJsonCount(1, 'data.controles_sugeridos')
        ->assertJsonPath('data.controles_sugeridos.0.codigo', 'A.8.13')
        ->assertJsonPath('data.controles_sugeridos.0.nombre', 'Respaldo de la información')
        ->assertJsonPath('data.controles_descartados', 1);

    Http::assertSent(fn (Request $peticion) => $peticion->hasHeader('x-goog-api-key', 'clave-de-prueba')
        && !str_contains($peticion->url(), 'key=')
        && str_contains($peticion->url(), 'models/gemini-3.5-flash:generateContent')
        && $peticion['generationConfig']['thinkingConfig']['thinkingLevel'] === 'low'
        && str_contains($peticion['contents'][0]['parts'][0]['text'], $this->activo->nombre));
});

it('reintenta una vez si Gemini responde 503 por alta demanda', function () {
    $json = json_encode(['controles_sugeridos' => [], 'probabilidad_sugerida' => 2, 'impacto_sugerido' => 4, 'justificacion_nivel' => 'x']);
    Http::fake([URL_GEMINI => Http::sequence()
        ->push(['error' => ['status' => 'UNAVAILABLE']], 503)
        ->push(respuestaGemini($json))]);

    $this->actingAs($this->admin)->postJson('/api/v1/ia/sugerir-riesgo', $this->datos)
        ->assertOk()
        ->assertJsonPath('data.impacto_sugerido', 4);

    Http::assertSentCount(2);
});

it('responde 503 IA_NO_DISPONIBLE si Gemini devuelve un error HTTP', function () {
    Http::fake([URL_GEMINI => Http::response(['error' => ['message' => 'quota']], 429)]);

    $this->actingAs($this->admin)->postJson('/api/v1/ia/sugerir-riesgo', $this->datos)
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'IA_NO_DISPONIBLE');
});

it('responde 503 IA_NO_DISPONIBLE si Gemini devuelve un JSON inválido', function () {
    Http::fake([URL_GEMINI => Http::response(respuestaGemini('esto no es JSON'))]);

    $this->actingAs($this->admin)->postJson('/api/v1/ia/sugerir-riesgo', $this->datos)
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'IA_NO_DISPONIBLE');
});

it('responde 503 sin llamar a Gemini si no hay API key configurada', function () {
    config(['sgsi.ia.gemini.key' => null]);
    Http::fake();

    $this->actingAs($this->admin)->postJson('/api/v1/ia/sugerir-riesgo', $this->datos)->assertStatus(503);

    Http::assertNothingSent();
});

it('no permite pedir sugerencias a quien no puede crear ni editar riesgos', function () {
    $rolConsulta = Rol::factory()->create(['nombre' => 'Consulta']);
    $rolConsulta->permisos()->attach(Permiso::query()->firstOrCreate(['clave' => 'riesgos.ver'], ['modulo' => 'riesgos'])->id);
    Http::fake();

    $this->actingAs(Usuario::factory()->create(['rol_id' => $rolConsulta->id]))
        ->postJson('/api/v1/ia/sugerir-riesgo', $this->datos)
        ->assertForbidden();

    Http::assertNothingSent();
});

it('valida que activo y amenaza existan', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/ia/sugerir-riesgo', ['activo_id' => 999999])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDACION_FALLIDA');
});
