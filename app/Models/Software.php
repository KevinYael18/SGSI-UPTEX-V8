<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Programa del catálogo centralizado de software. */
class Software extends Model
{
    protected $table = 'software_catalogo';

    protected $fillable = ['nombre', 'fabricante', 'categoria', 'tipo_licencia', 'estado_autorizacion', 'version_vigente', 'es_antivirus', 'descripcion'];

    protected $casts = ['es_antivirus' => 'boolean'];

    /** Equipos donde el programa está INSTALADO actualmente. */
    public function computadoras(): BelongsToMany
    {
        return $this->computadorasHistorico()->wherePivot('estado_instalacion', 'instalado');
    }

    /** Todas las instalaciones registradas, incluidas las desinstaladas (historial). */
    public function computadorasHistorico(): BelongsToMany
    {
        return $this->belongsToMany(Computadora::class, 'computadora_software', 'software_id', 'computadora_id')
            ->using(InstalacionSoftware::class)
            ->withPivot(['id', 'estado_instalacion', 'version', 'fecha_instalacion', 'estado_licencia', 'requiere_actualizacion', 'observaciones'])
            ->withTimestamps();
    }

    /** Activo de tipo software que representa a este programa (si existe). */
    public function activo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }
}
