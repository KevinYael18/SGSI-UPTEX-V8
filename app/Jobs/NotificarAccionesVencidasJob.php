<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\AccionCorrectivaVencida;
use App\Repositories\Contracts\AccionCorrectivaRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado por el Scheduler (php artisan schedule:work, corriendo dentro
 * del único contenedor `app` vía supervisord — ver docs/02...). Marca
 * vencidas las acciones correctivas fuera de plazo; la notificación real
 * (BD + correo) la genera el listener NotificarPorEventoDominio al
 * escuchar AccionCorrectivaVencida (GeneraNotificacion), no este Job.
 */
class NotificarAccionesVencidasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AccionCorrectivaRepositoryInterface $acciones): void
    {
        foreach ($acciones->vencidasNoMarcadas() as $accion) {
            $accion->update(['estado' => 'vencida']);
            event(new AccionCorrectivaVencida($accion));
        }
    }
}