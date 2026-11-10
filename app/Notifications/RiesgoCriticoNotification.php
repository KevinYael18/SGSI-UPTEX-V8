<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Riesgo;
use App\Notifications\Channels\BaseDatosSgsiChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RiesgoCriticoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Riesgo $riesgo)
    {
    }

    public function via(object $notifiable): array
    {
        return [BaseDatosSgsiChannel::class, 'mail'];
    }

    public function toBaseDatosSgsi(object $notifiable): array
    {
        return [
            'tipo' => 'riesgo_critico_detectado',
            'datos' => [
                'riesgo_id' => $this->riesgo->id,
                'activo_id' => $this->riesgo->activo_id,
                'mensaje' => "Se detectó un riesgo crítico (#{$this->riesgo->id}) sin tratamiento asignado.",
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('SGSI UPTex — Riesgo crítico sin tratamiento')
            ->greeting("Hola {$notifiable->nombre},")
            ->line("Se detectó un riesgo crítico (#{$this->riesgo->id}) sin tratamiento asignado.")
            ->action('Revisar riesgo', url("/riesgos/{$this->riesgo->id}"))
            ->line('Por favor asigne un tratamiento (Mitigar, Transferir, Evitar o Aceptar) lo antes posible.');
    }
}