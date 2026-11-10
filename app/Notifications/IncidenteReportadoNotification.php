<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Incidente;
use App\Notifications\Channels\BaseDatosSgsiChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IncidenteReportadoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Incidente $incidente)
    {
    }

    public function via(object $notifiable): array
    {
        return [BaseDatosSgsiChannel::class, 'mail'];
    }

    public function toBaseDatosSgsi(object $notifiable): array
    {
        return [
            'tipo' => 'incidente_reportado',
            'datos' => [
                'incidente_uuid' => $this->incidente->uuid,
                'titulo' => $this->incidente->titulo,
                'mensaje' => "Se reportó un nuevo incidente sobre un activo bajo su responsabilidad: {$this->incidente->titulo}",
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('SGSI UPTex — Nuevo incidente reportado')
            ->line("Se reportó un nuevo incidente: {$this->incidente->titulo}");
    }
}