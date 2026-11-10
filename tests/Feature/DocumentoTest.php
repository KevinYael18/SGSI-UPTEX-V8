<?php

declare(strict_types=1);

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);
    $this->rolAdmin = Rol::factory()->create(['nombre' => 'Administrador']);
    $this->autor = Usuario::factory()->create(['rol_id' => $this->rolAdmin->id]);
});

it('sube un documento nuevo en estado borrador', function () {
    $archivo = UploadedFile::fake()->create('politica.pdf', 200, 'application/pdf');

    $respuesta = $this->actingAs($this->autor)->postJson('/api/v1/documentos', [
        'titulo' => 'Política de Seguridad de la Información',
        'archivo' => $archivo,
    ]);

    $respuesta->assertCreated()->assertJsonPath('data.estado', 'borrador');
});

it('no permite que el autor de la version apruebe su propio documento', function () {
    $documento = \App\Models\Documento::factory()->create(['estado' => 'en_revision']);
    $documento->versiones()->create(['creado_por' => $this->autor->id, 'numero_version' => 1, 'path_storage' => 'x']);

    $respuesta = $this->actingAs($this->autor)->patchJson("/api/v1/documentos/{$documento->uuid}/estado", ['estado' => 'aprobado']);

    $respuesta->assertStatus(403);
});

it('respeta la maquina de estados del workflow documental', function () {
    $aprobador = Usuario::factory()->create(['rol_id' => $this->rolAdmin->id]);
    $documento = \App\Models\Documento::factory()->create(['estado' => 'borrador']);

    $respuesta = $this->actingAs($aprobador)->patchJson("/api/v1/documentos/{$documento->uuid}/estado", ['estado' => 'publicado']);

    $respuesta->assertStatus(422)->assertJsonPath('error.code', 'TRANSICION_ESTADO_INVALIDA');
});