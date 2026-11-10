<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activo;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * El QR codifica la URL de "ficha del activo" (por UUID, no ID secuencial
 * — coherente con la decisión de Fase 1.5 §8), para que escanearlo con
 * cualquier lector estándar lleve directo al detalle del activo, sin
 * depender de que el lector sea la app del SGSI.
 */
class QrCodeService
{
    public function generarSvg(Activo $activo): string
    {
        $url = config('app.url') . "/activos/{$activo->uuid}";

        return (string) QrCode::format('svg')->size(300)->generate($url);
    }

    public function urlCodificada(Activo $activo): string
    {
        return config('app.url') . "/activos/{$activo->uuid}";
    }
}