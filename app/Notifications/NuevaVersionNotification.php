<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DocumentoVersion;
use App\Notifications\Channels\BaseDatosSgsiChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NuevaVersionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly DocumentoVersion $version)
    {
    }

    public function via(object $notifiable): array
    {
        return [BaseDatosSgsiChannel::class, 'mail'];
    }

    public function toBaseDatosSgsi(object $notifiable): array
    {
        return [
            'tipo' => 'nueva_version_documento',
            'datos' => [
                'documento_id' => $this->version->documento_id,
                'numero_version' => $this->version->numero_version,
                'mensaje' => "Hay una nueva versión (v{$this->version->numero_version}) pendiente de revisión.",
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('SGSI UPTex — Nueva versión de documento pendiente de revisión')
            ->line("Hay una nueva versión (v{$this->version->numero_version}) pendiente de revisión.");
    }
}