<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Instalación de un programa del catálogo en una computadora concreta
 * (versión, licencia y estado propios de ESA instalación).
 */
class InstalacionSoftware extends Pivot
{
    protected $table = 'computadora_software';

    public $incrementing = true;

    protected $casts = [
        'requiere_actualizacion' => 'boolean',
        'fecha_instalacion' => 'date',
    ];

    /**
     * Desactualizada si se marcó manualmente, o si el catálogo define una
     * versión vigente y la instalada es menor (comparación de versiones).
     */
    public static function estaDesactualizada(bool $marcada, ?string $instalada, ?string $vigente): bool
    {
        if ($marcada) {
            return true;
        }
        if ($instalada === null || $vigente === null || trim($instalada) === '' || trim($vigente) === '') {
            return false;
        }

        return version_compare(self::normalizar($instalada), self::normalizar($vigente), '<');
    }

    private static function normalizar(string $version): string
    {
        // "Versión 2024.1 (x64)" -> "2024.1"
        return preg_match('/\d+(?:\.\d+)*/', $version, $m) ? $m[0] : $version;
    }
}
