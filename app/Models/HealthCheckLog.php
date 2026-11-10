<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthCheckLog extends Model
{
    protected $table = 'health_check_log';
    public $timestamps = false;
    protected $fillable = ['estado_bd', 'estado_cola', 'estado_storage', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];
}