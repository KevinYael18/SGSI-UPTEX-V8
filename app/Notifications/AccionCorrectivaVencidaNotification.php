<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AccionCorrectiva;
use App\Notifications\Channels\BaseDatosSgsiChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccionCorrectivaVencidaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly AccionCorrectiva $accion)
    {
    }

    public function via(object $notifiable): array
    {
        return [BaseDatosSgsiChannel::class, 'mail'];
    }

    public function toBaseDatosSgsi(object $notifiable): array
    {
        return [
            'tipo' => 'accion_correctiva_vencida',
            'datos' => [
                'accion_correctiva_id' => $this->accion->id,
                'mensaje' => "La acción correctiva #{$this->accion->id} venció el {$this->accion->fecha_compromiso->toDateString()}.",
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('SGSI UPTex — Acción correctiva vencida')
            ->greeting("Hola {$notifiable->nombre},")
            ->line("La acción correctiva #{$this->accion->id} bajo su responsabilidad venció el {$this->accion->fecha_compromiso->toDateString()}.")
            ->action('Ver acción correctiva', url("/acciones-correctivas/{$this->accion->id}"));
    }
}