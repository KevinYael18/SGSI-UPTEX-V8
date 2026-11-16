<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo de recuperación de contraseña (Password Broker nativo de Laravel).
 *
 * Se dispara desde Usuario::sendPasswordResetNotification() cuando
 * AuthController::solicitarRecuperacion() llama a Password::sendResetLink().
 *
 * Es SÍNCRONA a propósito (no ShouldQueue): la API solo confirma al usuario
 * después de que el servidor SMTP aceptó el mensaje. Si el envío falla, la
 * excepción llega al controlador y el usuario recibe un error real en lugar
 * de un "correo enviado" falso.
 *
 * Plantillas: resources/views/emails/restablecer-password.blade.php (HTML,
 * tablas + estilos en línea para Gmail/Outlook/móviles) y
 * resources/views/emails/restablecer-password-texto.blade.php (texto plano).
 */
class RestablecerPasswordNotification extends Notification
{
    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * El enlace SIEMPRE se construye con APP_URL (nunca con el Host de la
     * petición) para evitar el envenenamiento de enlaces de recuperación
     * mediante cabeceras Host manipuladas.
     */
    public function urlRestablecimiento(object $notifiable): string
    {
        return rtrim((string) config('app.url'), '/')
            . '/restablecer-password?token=' . urlencode($this->token)
            . '&email=' . urlencode((string) $notifiable->getEmailForPasswordReset());
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutos = (int) config('auth.passwords.' . config('auth.defaults.passwords', 'users') . '.expire', 60);

        return (new MailMessage())
            ->subject('Restablece tu contraseña · SGSI UPTex')
            ->view(
                [
                    'html' => 'emails.restablecer-password',
                    'text' => 'emails.restablecer-password-texto',
                ],
                [
                    'nombre' => (string) ($notifiable->nombre ?? ''),
                    'correo' => (string) $notifiable->getEmailForPasswordReset(),
                    'url' => $this->urlRestablecimiento($notifiable),
                    'vigencia' => self::describirVigencia($minutos),
                    'solicitadoEn' => now()->locale('es')->translatedFormat('j \d\e F \d\e Y, H:i') . ' h',
                    'institucion' => (string) config('sgsi.correo.institucion', 'Universidad Politécnica de Texcoco'),
                    'sitioWeb' => (string) config('sgsi.correo.sitio_web', ''),
                ]
            );
    }

    /** "60 minutos" -> "1 hora"; "90" -> "90 minutos"; "1440" -> "24 horas". */
    public static function describirVigencia(int $minutos): string
    {
        if ($minutos > 0 && $minutos % 60 === 0) {
            $horas = intdiv($minutos, 60);

            return $horas === 1 ? '1 hora' : "{$horas} horas";
        }

        return $minutos === 1 ? '1 minuto' : "{$minutos} minutos";
    }
}
