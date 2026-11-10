<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;

class FeatureFlagsSeeder extends Seeder
{
    public function run(): void
    {
        $flags = [
            ['clave' => 'integracion_sistema_escolar', 'descripcion' => 'Integración con el Sistema Escolar institucional (stub)'],
            ['clave' => 'integracion_moodle', 'descripcion' => 'Integración con Moodle (stub)'],
            ['clave' => 'integracion_ad_ldap', 'descripcion' => 'Autenticación contra AD/LDAP institucional (stub)'],
            ['clave' => 'almacenamiento_minio', 'descripcion' => 'Usar MinIO (S3) en vez de disco local para evidencias/documentos'],
        ];

        foreach ($flags as $f) {
            FeatureFlag::query()->firstOrCreate(['clave' => $f['clave']], ['activo' => false, 'descripcion' => $f['descripcion']]);
        }
    }
}