<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'SGSI UPTex API',
    description: 'API REST del Sistema de Gestión de Seguridad de la Información — Universidad Politécnica de Texcoco (ISO/IEC 27001:2022).'
)]
#[OA\Server(url: '/api/v1', description: 'Servidor principal versionado')]
#[OA\SecurityScheme(securityScheme: 'sanctum', type: 'apiKey', in: 'cookie', name: 'laravel_session')]
final class BaseInfo
{
    // Clase contenedora de anotaciones globales para l5-swagger;
    // no contiene lógica. `php artisan l5-swagger:generate` la escanea
    // junto con los Controllers para producir docs/openapi.json.
}