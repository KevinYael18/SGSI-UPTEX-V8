<?php

/**
 * SGSI UPTex — Ajustes de configuración de correo y recuperación de contraseña
 * sobre el esqueleto oficial de Laravel 12 (que este repositorio no versiona,
 * ver Dockerfile etapa composer-build).
 *
 * Se ejecuta en el build:  php docker/laravel/configurar-correo.php /ruta/al/proyecto
 *
 * Qué corrige:
 *  1) Laravel 12 IGNORA la variable MAIL_ENCRYPTION (el esqueleto solo lee
 *     MAIL_SCHEME). Aquí MAIL_ENCRYPTION vuelve a tener efecto:
 *        ssl | smtps  -> conexión TLS implícita (puerto 465)
 *        tls          -> STARTTLS obligatorio (puerto 587); si el servidor no
 *                        ofrece STARTTLS el envío falla en vez de mandar la
 *                        contraseña SMTP en texto plano
 *        vacío | null -> sin cifrado forzado (Mailpit / SMTP local de pruebas)
 *     MAIL_SCHEME (smtp|smtps), si se define, tiene prioridad.
 *  2) Tiempo de espera SMTP acotado (MAIL_TIMEOUT, 15 s por defecto) para que
 *     un servidor inalcanzable no deje colgada la petición 60 s.
 *  2b) MAIL_EHLO_DOMAIN vacío ya no produce un EHLO sin argumento (Gmail lo
 *      rechaza con 501): se usa el host de APP_URL.
 *  3) Vigencia y espera entre solicitudes del token de recuperación
 *     configurables por .env (AUTH_PASSWORD_RESET_EXPIRE / _THROTTLE).
 *
 * Ninguna credencial se escribe aquí: todo se lee de variables de entorno.
 * Si el esqueleto cambia y un patrón ya no existe, el build FALLA (exit 1)
 * en lugar de continuar silenciosamente con una configuración incompleta.
 */

$raiz = rtrim($argv[1] ?? getcwd(), '/');

function reemplazarUnaVez(string $archivo, string $buscar, string $reemplazo): void
{
    $contenido = file_get_contents($archivo);
    if ($contenido === false) {
        fwrite(STDERR, "[configurar-correo] No se pudo leer {$archivo}\n");
        exit(1);
    }
    if (str_contains($contenido, $reemplazo)) {
        return; // ya aplicado (idempotente)
    }
    if (substr_count($contenido, $buscar) !== 1) {
        fwrite(STDERR, "[configurar-correo] Patrón no encontrado (o ambiguo) en {$archivo}: {$buscar}\n");
        exit(1);
    }
    file_put_contents($archivo, str_replace($buscar, $reemplazo, $contenido));
}

// ---- config/mail.php -------------------------------------------------------
$mail = "{$raiz}/config/mail.php";

reemplazarUnaVez(
    $mail,
    "'scheme' => env('MAIL_SCHEME'),",
    "'scheme' => env('MAIL_SCHEME') ?: (in_array(strtolower((string) env('MAIL_ENCRYPTION')), ['ssl', 'smtps'], true) ? 'smtps' : null),\n"
    . "            // SGSI UPTex: con MAIL_ENCRYPTION=tls se exige STARTTLS (nunca credenciales en texto plano).\n"
    . "            'require_tls' => strtolower((string) env('MAIL_ENCRYPTION')) === 'tls',"
);

reemplazarUnaVez(
    $mail,
    "'timeout' => null,",
    "'timeout' => (int) env('MAIL_TIMEOUT', 15),"
);

// MAIL_EHLO_DOMAIN vacío en el .env hace que env() devuelva '' (no el default)
// y el EHLO sale sin argumento: Gmail responde "501 Empty HELO/EHLO argument
// not allowed" y NINGÚN correo se envía. Vacío => host de APP_URL => localhost.
reemplazarUnaVez(
    $mail,
    "'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),",
    "'local_domain' => env('MAIL_EHLO_DOMAIN') ?: (parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),"
);

// ---- config/auth.php -------------------------------------------------------
$auth = "{$raiz}/config/auth.php";

reemplazarUnaVez(
    $auth,
    "'expire' => 60,",
    "'expire' => (int) env('AUTH_PASSWORD_RESET_EXPIRE', 60),"
);
reemplazarUnaVez(
    $auth,
    "'throttle' => 60,",
    "'throttle' => (int) env('AUTH_PASSWORD_RESET_THROTTLE', 60),"
);

fwrite(STDOUT, "[configurar-correo] config/mail.php y config/auth.php ajustados.\n");
