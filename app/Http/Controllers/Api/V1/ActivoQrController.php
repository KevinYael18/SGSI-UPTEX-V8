<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activo;
use App\Services\QrCodeService;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class ActivoQrController extends Controller
{
    public function __construct(private readonly QrCodeService $service)
    {
    }

    #[OA\Get(
        path: '/api/v1/activos/{activo}/qr',
        tags: ['Activos'],
        summary: 'Genera el código QR (SVG) del activo para inventario físico — imprimible como etiqueta'
    )]
    public function __invoke(Activo $activo): Response
    {
        $this->authorize('view', $activo);

        return response($this->service->generarSvg($activo), 200)->header('Content-Type', 'image/svg+xml');
    }
}