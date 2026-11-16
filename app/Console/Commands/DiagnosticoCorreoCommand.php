<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;

/**
 * Diagnóstico de la configuración de correo, paso por paso:
 * configuración efectiva -> DNS -> conexión TCP -> TLS/autenticación SMTP
 * -> (opcional) envío real de un correo de prueba.
 *
 *   php artisan sgsi:correo:diagnostico
 *   php artisan sgsi:correo:diagnostico --enviar=destinatario@dominio
 *
 * Nunca imprime la contraseña SMTP.
 */
class DiagnosticoCorreoCommand extends Command
{
    protected $signature = 'sgsi:correo:diagnostico {--enviar= : Correo al que se enviará un mensaje de prueba real}';

    protected $description = 'Comprueba la configuración SMTP (DNS, puerto, TLS, autenticación) y opcionalmente envía un correo de prueba';

    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $smtp = (array) config('mail.mailers.smtp');
        $host = (string) ($smtp['host'] ?? '');
        $puerto = (int) ($smtp['port'] ?? 0);

        $this->info('1) Configuración efectiva (la que Laravel está usando ahora, incluida la caché de config)');
        $this->table(['Variable', 'Valor'], [
            ['MAIL_MAILER', $mailer],
            ['MAIL_HOST', $host ?: '(vacío)'],
            ['MAIL_PORT', (string) $puerto],
            ['MAIL_ENCRYPTION / esquema', ($smtp['scheme'] ?? null) ?: ($puerto === 465 ? 'smtps (automático por puerto 465)' : 'smtp + STARTTLS si el servidor lo ofrece')],
            ['STARTTLS obligatorio', !empty($smtp['require_tls']) ? 'sí' : 'no'],
            ['MAIL_USERNAME', ($smtp['username'] ?? null) ?: '(sin usuario)'],
            ['MAIL_PASSWORD', !empty($smtp['password']) ? '******** (definida)' : '(vacía)'],
            ['MAIL_FROM_ADDRESS', (string) config('mail.from.address')],
            ['APP_URL (enlaces de los correos)', (string) config('app.url')],
            ['Config en caché', app()->configurationIsCached() ? 'sí — tras editar .env ejecuta php artisan config:clear o reinicia el contenedor' : 'no'],
        ]);

        if ($mailer === 'log' || $mailer === 'array') {
            $this->error("MAIL_MAILER={$mailer}: los correos NO se entregan a ningún buzón. Configura MAIL_MAILER=smtp.");

            return self::FAILURE;
        }
        if ($mailer !== 'smtp') {
            $this->warn("Mailer «{$mailer}»: este diagnóstico solo prueba conexiones SMTP.");
        }
        if (str_contains((string) config('app.url'), 'localhost') || str_contains((string) config('app.url'), '127.0.0.1')) {
            $this->warn('APP_URL apunta a localhost: los enlaces del correo solo funcionarán en ESTA máquina. En red usa la IP o el dominio del servidor.');
        }

        $this->info("2) Resolución DNS de «{$host}»");
        $ip = gethostbyname($host);
        if ($host === '' || $ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            $this->error("No se pudo resolver «{$host}». Dentro de Docker, «mailpit» solo existe si el contenedor sgsi-uptex-mailpit está corriendo; «localhost» es el propio contenedor de la app, no tu equipo.");

            return self::FAILURE;
        }
        $this->line("   {$host} -> {$ip}");

        $this->info("3) Conexión TCP a {$host}:{$puerto}");
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $puerto, $errno, $errstr, 8);
        if ($socket === false) {
            $this->error("No hay conexión ({$errno}: {$errstr}). Revisa firewall, que el puerto sea el correcto y que el proveedor permita conexiones desde este servidor.");

            return self::FAILURE;
        }
        fclose($socket);
        $this->line('   Puerto accesible.');

        $this->info('4) Saludo SMTP, cifrado TLS y autenticación');
        try {
            $transport = Mail::mailer('smtp')->getSymfonyTransport();
            if ($transport instanceof EsmtpTransport) {
                $transport->start();
                $transport->stop();
            }
            $this->line('   El servidor aceptó la conexión' . (!empty($smtp['username']) ? ' y las credenciales.' : '.'));
        } catch (Throwable $e) {
            $this->error('Fallo SMTP: ' . $e->getMessage());
            $this->line('   Pistas: 587 => MAIL_ENCRYPTION=tls; 465 => MAIL_ENCRYPTION=ssl; Gmail/Microsoft 365 requieren contraseña de aplicación o SMTP AUTH habilitado.');

            return self::FAILURE;
        }

        $destino = $this->option('enviar');
        if ($destino) {
            if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
                $this->error('El destinatario no es un correo válido.');

                return self::FAILURE;
            }
            $this->info("5) Envío real de prueba a {$destino}");
            try {
                Mail::raw(
                    "Correo de prueba de SGSI UPTex.\nSi lo recibiste, la configuración SMTP funciona.\nEnviado: " . now()->toDateTimeString(),
                    fn ($m) => $m->to($destino)->subject('Prueba de correo · SGSI UPTex')
                );
                $this->info('   El servidor SMTP aceptó el mensaje. Revisa la bandeja de entrada (y la de no deseados).');
            } catch (Throwable $e) {
                $this->error('El servidor rechazó el envío: ' . $e->getMessage());

                return self::FAILURE;
            }
        } else {
            $this->line('Para un envío real de prueba: php artisan sgsi:correo:diagnostico --enviar=tu@correo');
        }

        return self::SUCCESS;
    }
}
