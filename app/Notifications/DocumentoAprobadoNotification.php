<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Documento;
use App\Notifications\Channels\BaseDatosSgsiChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentoAprobadoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Documento $documento)
    {
    }

    public function via(object $notifiable): array
    {
        return [BaseDatosSgsiChannel::class, 'mail'];
    }

    public function toBaseDatosSgsi(object $notifiable): array
    {
        return [
            'tipo' => 'documento_aprobado',
            'datos' => [
                'documento_uuid' => $this->documento->uuid,
                'mensaje' => "El documento \"{$this->documento->titulo}\" fue aprobado y está listo para publicarse.",
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('SGSI UPTex — Documento aprobado')
            ->line("El documento \"{$this->documento->titulo}\" fue aprobado.");
    }
}