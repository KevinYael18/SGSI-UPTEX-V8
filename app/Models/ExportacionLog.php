<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportacionLog extends Model
{
    protected $table = 'exportaciones_log';
    public $timestamps = false;
    protected $fillable = ['usuario_id', 'modulo', 'formato', 'filtros', 'created_at'];

    protected $casts = ['filtros' => 'array', 'created_at' => 'datetime'];
}