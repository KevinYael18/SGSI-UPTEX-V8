<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RestablecerPasswordNotification extends Notification
{
    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = config('app.url') . '/restablecer-password?token=' . $this->token . '&email=' . urlencode($notifiable->email);

        return (new MailMessage())
            ->subject('Recuperación de contraseña — SGSI UPTex')
            ->greeting('Hola, ' . $notifiable->nombre . '.')
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta en el Sistema de Gestión de Seguridad de la Información de la UPTex.')
            ->action('Restablecer contraseña', $url)
            ->line('Este enlace expira en 60 minutos por tu seguridad.')
            ->line('Si tú no solicitaste este cambio, puedes ignorar este correo — tu contraseña actual seguirá funcionando sin ningún cambio.')
            ->salutation('Atentamente, el equipo del SGSI UPTex');
    }
}