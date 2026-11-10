<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ConfiguracionSistema;
use Illuminate\Database\Seeder;

class ConfiguracionSistemaSeeder extends Seeder
{
    public function run(): void
    {
        $valores = [
            ['clave' => 'nombre_institucion', 'valor' => 'Universidad Politécnica de Texcoco', 'tipo' => 'texto', 'descripcion' => 'Nombre mostrado en reportes y encabezados'],
            ['clave' => 'dias_alerta_documentos_por_vencer', 'valor' => '30', 'tipo' => 'numero', 'descripcion' => 'Días de anticipación para alertar vencimiento de documentos'],
            ['clave' => 'dias_bloqueo_login_fallido', 'valor' => '15', 'tipo' => 'numero', 'descripcion' => 'Minutos de bloqueo tras 5 intentos fallidos de login'],
            ['clave' => 'politica_retencion_evidencias_dias', 'valor' => '365', 'tipo' => 'numero', 'descripcion' => 'Días de retención por defecto para evidencias de incidentes'],
        ];

        foreach ($valores as $v) {
            ConfiguracionSistema::query()->firstOrCreate(['clave' => $v['clave']], $v);
        }
    }
}