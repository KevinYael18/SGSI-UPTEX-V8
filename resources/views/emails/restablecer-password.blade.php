{{--
    SGSI UPTex — Correo institucional: restablecimiento de contraseña (HTML).

    Compatibilidad: Gmail (web/app), Outlook (escritorio 2016+ / web), Apple
    Mail y clientes móviles. Por eso se usan tablas, estilos EN LÍNEA, ancho
    máximo de 600 px y un botón "a prueba de balas" con VML para Outlook.

    El logotipo se incrusta como imagen CID (adjunto en línea), no como URL
    ni como data: URI — Gmail y Outlook bloquean las data: URI, y una URL
    local (localhost / IP de la red Wi-Fi) no es accesible desde Internet.

    Variables: $nombre, $correo, $url, $vigencia, $solicitadoEn,
               $institucion, $sitioWeb (opcional), $message (Illuminate\Mail\Message)
--}}
@php
    $rutaLogo = resource_path('images/correo/logo-uptex.png');
    $logo = (isset($message) && $message instanceof \Illuminate\Mail\Message && is_file($rutaLogo))
        ? $message->embed($rutaLogo)
        : null;
    $primerNombre = trim(explode(' ', trim($nombre))[0] ?? '');
    $anio = now()->year;
    $verde = '#0A7A3D';
    $verdeOscuro = '#075E2F';
    $tinta = '#161B1D';
    $texto = '#3D474C';
    $tenue = '#6B747B';
    $fuente = "'Segoe UI', Roboto, 'Helvetica Neue', Helvetica, Arial, sans-serif";
@endphp
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Restablece tu contraseña · SGSI UPTex</title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <style>table, td, p, a, span { font-family: Arial, Helvetica, sans-serif !important; }</style>
    <![endif]-->
    <style>
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        a { text-decoration: none; }
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; }
        .boton-enlace:hover { background-color: {{ $verdeOscuro }} !important; border-color: {{ $verdeOscuro }} !important; }
        @media only screen and (max-width: 620px) {
            .contenedor { width: 100% !important; }
            .px { padding-left: 24px !important; padding-right: 24px !important; }
            .encabezado-col { display: block !important; width: 100% !important; text-align: center !important; }
            .encabezado-sello { padding-top: 14px !important; }
            .titulo { font-size: 23px !important; line-height: 30px !important; }
            .exterior { padding: 16px 8px !important; }
            .boton-tabla { width: 100% !important; }
            .boton-celda { width: 100% !important; }
            .boton-enlace { display: block !important; min-width: 0 !important; width: auto !important; box-sizing: border-box !important; padding-left: 12px !important; padding-right: 12px !important; font-size: 14px !important; letter-spacing: 0.6px !important; }
            .dato-col { display: block !important; width: 100% !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#EEF2EF;">
    {{-- Preheader: texto de vista previa en la bandeja de entrada (oculto en el cuerpo). --}}
    <div style="display:none; font-size:1px; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden; mso-hide:all;">
        Usa el enlace seguro para crear una nueva contraseña de SGSI UPTex. Es válido durante {{ $vigencia }} y solo funciona una vez.
        &#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#EEF2EF;">
        <tr>
            <td class="exterior" align="center" style="padding:32px 12px;">
                <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" align="center"><tr><td><![endif]-->
                <table role="presentation" class="contenedor" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#FFFFFF; border:1px solid #DDE4E0; border-radius:14px; overflow:hidden;">

                    {{-- Franja institucional (verde · lima · rojo · gris), igual que en el sistema --}}
                    <tr>
                        <td style="padding:0; font-size:0; line-height:0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="40%" height="5" style="background-color:#0A7A3D; font-size:0; line-height:0;">&nbsp;</td>
                                    <td width="24%" height="5" style="background-color:#7AB51D; font-size:0; line-height:0;">&nbsp;</td>
                                    <td width="20%" height="5" style="background-color:#C8102E; font-size:0; line-height:0;">&nbsp;</td>
                                    <td width="16%" height="5" style="background-color:#9AA2A8; font-size:0; line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Encabezado: logotipo + identificación del sistema --}}
                    <tr>
                        <td class="px" style="padding:26px 40px 22px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td class="encabezado-col" valign="middle" width="50%" style="text-align:left;">
                                        @if ($logo)
                                            <img src="{{ $logo }}" width="150" height="70" alt="UPTex — {{ $institucion }}" style="display:inline-block; width:150px; max-width:150px; height:auto; border:0;">
                                        @else
                                            <span style="font-family:{{ $fuente }}; font-size:26px; font-weight:800; letter-spacing:-0.5px; color:{{ $verde }};">UPTex</span>
                                        @endif
                                    </td>
                                    <td class="encabezado-col encabezado-sello" valign="middle" width="50%" style="text-align:right;">
                                        <span style="display:inline-block; font-family:{{ $fuente }}; font-size:11px; line-height:14px; font-weight:700; letter-spacing:1.6px; text-transform:uppercase; color:{{ $verde }}; background-color:#E5F3EA; border:1px solid #CBE6D4; border-radius:999px; padding:6px 12px;">SGSI&nbsp;UPTex</span>
                                        <div style="font-family:{{ $fuente }}; font-size:12px; line-height:17px; color:{{ $tenue }}; padding-top:8px;">
                                            Sistema de Gestión de<br>Seguridad de la Información
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Banda principal --}}
                    <tr>
                        <td style="padding:0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td class="px" bgcolor="{{ $verde }}" style="padding:30px 40px 30px 40px; background-color:{{ $verde }}; background-image:linear-gradient(135deg, #075E2F 0%, #0A7A3D 55%, #3E9A2C 100%);">
                                        <div style="font-family:{{ $fuente }}; font-size:11px; line-height:14px; font-weight:700; letter-spacing:1.8px; text-transform:uppercase; color:#CDEBD7;">
                                            Seguridad de tu cuenta
                                        </div>
                                        <h1 class="titulo" style="margin:8px 0 0 0; font-family:{{ $fuente }}; font-size:26px; line-height:33px; font-weight:700; color:#FFFFFF;">
                                            Restablecimiento de contraseña
                                        </h1>
                                        <div style="font-family:{{ $fuente }}; font-size:13px; line-height:19px; color:#DDF1E4; padding-top:6px;">
                                            {{ $institucion }}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Cuerpo --}}
                    <tr>
                        <td class="px" style="padding:34px 40px 8px 40px; font-family:{{ $fuente }};">
                            <p style="margin:0 0 16px 0; font-size:18px; line-height:26px; font-weight:600; color:{{ $tinta }};">
                                Hola{{ $primerNombre !== '' ? ', ' . $primerNombre : '' }}.
                            </p>
                            <p style="margin:0 0 14px 0; font-size:15px; line-height:24px; color:{{ $texto }};">
                                Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en <strong style="color:{{ $tinta }};">SGSI UPTex</strong>.
                            </p>
                            <p style="margin:0 0 26px 0; font-size:15px; line-height:24px; color:{{ $texto }};">
                                Para continuar, presiona el siguiente botón. Se abrirá una página segura del sistema en la que podrás establecer tu nueva contraseña.
                            </p>
                        </td>
                    </tr>

                    {{-- Botón principal (VML para Outlook de escritorio) --}}
                    <tr>
                        <td class="px" align="center" style="padding:0 40px 10px 40px;">
                            <table class="boton-tabla" role="presentation" cellpadding="0" cellspacing="0" border="0" align="center">
                                <tr>
                                    <td class="boton-celda" align="center" bgcolor="{{ $verde }}" style="border-radius:10px; background-color:{{ $verde }};">
                                        <!--[if mso]>
                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height:52px; v-text-anchor:middle; width:330px;" arcsize="20%" stroke="f" fillcolor="{{ $verde }}">
                                            <w:anchorlock/>
                                            <center style="color:#FFFFFF; font-family:Arial, sans-serif; font-size:15px; font-weight:bold; letter-spacing:1px;">RESTABLECER MI CONTRASEÑA</center>
                                        </v:roundrect>
                                        <![endif]-->
                                        <!--[if !mso]><!-->
                                        <a href="{{ $url }}" target="_blank" rel="noopener" class="boton-enlace"
                                           style="display:inline-block; min-width:280px; padding:16px 34px; font-family:{{ $fuente }}; font-size:15px; line-height:20px; font-weight:700; letter-spacing:1px; color:#FFFFFF; text-decoration:none; text-align:center; background-color:{{ $verde }}; border:1px solid {{ $verde }}; border-radius:10px; box-shadow:0 8px 18px -8px rgba(10,122,61,0.65);">
                                            RESTABLECER MI CONTRASEÑA
                                        </a>
                                        <!--<![endif]-->
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Vigencia y uso único --}}
                    <tr>
                        <td class="px" style="padding:20px 40px 0 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F8F5; border:1px solid #E1EBE4; border-radius:10px;">
                                <tr>
                                    <td class="dato-col" width="50%" valign="top" style="padding:16px 18px; font-family:{{ $fuente }};">
                                        <div style="font-size:11px; line-height:14px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; color:{{ $tenue }};">Vigencia del enlace</div>
                                        <div style="font-size:15px; line-height:22px; font-weight:600; color:{{ $tinta }}; padding-top:4px;">{{ $vigencia }} · un solo uso</div>
                                    </td>
                                    <td class="dato-col" width="50%" valign="top" style="padding:16px 18px; font-family:{{ $fuente }};">
                                        <div style="font-size:11px; line-height:14px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; color:{{ $tenue }};">Cuenta</div>
                                        <div style="font-size:15px; line-height:22px; font-weight:600; color:{{ $tinta }}; padding-top:4px; word-break:break-all;">{{ $correo }}</div>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:12px 0 0 0; font-family:{{ $fuente }}; font-size:13px; line-height:20px; color:{{ $tenue }};">
                                Después de {{ $vigencia }}, o en cuanto lo utilices, el enlace dejará de funcionar y tendrás que solicitar uno nuevo. Solicitud recibida el {{ $solicitadoEn }}.
                            </p>
                        </td>
                    </tr>

                    {{-- Aviso de seguridad --}}
                    <tr>
                        <td class="px" style="padding:24px 40px 0 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FFF8EC; border:1px solid #F3DFB8; border-left:4px solid #B86E00; border-radius:10px;">
                                <tr>
                                    <td style="padding:16px 18px; font-family:{{ $fuente }};">
                                        <div style="font-size:14px; line-height:20px; font-weight:700; color:#7A4A00;">¿No solicitaste este cambio?</div>
                                        <p style="margin:6px 0 0 0; font-size:13px; line-height:20px; color:#5C4A2E;">
                                            Ignora este mensaje: tu contraseña actual seguirá funcionando y nadie podrá cambiarla sin este enlace. Si recibes solicitudes que no reconoces, avisa al Responsable del SGSI o al área de Tecnologías de la Información de la universidad.
                                        </p>
                                        <p style="margin:10px 0 0 0; font-size:13px; line-height:20px; color:#5C4A2E;">
                                            <strong style="color:#7A4A00;">Importante:</strong> nadie de la {{ $institucion }} te pedirá tu contraseña por correo electrónico, teléfono ni mensaje. Nunca la compartas.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Enlace alternativo --}}
                    <tr>
                        <td class="px" style="padding:24px 40px 34px 40px; font-family:{{ $fuente }};">
                            <p style="margin:0; font-size:13px; line-height:20px; color:{{ $tenue }};">
                                ¿El botón no funciona? Copia y pega este enlace en tu navegador:
                            </p>
                            <p style="margin:6px 0 0 0; font-size:12px; line-height:18px; word-break:break-all;">
                                <a href="{{ $url }}" target="_blank" rel="noopener" style="color:{{ $verde }}; text-decoration:underline;">{{ $url }}</a>
                            </p>
                        </td>
                    </tr>

                    {{-- Pie institucional --}}
                    <tr>
                        <td class="px" style="padding:24px 40px 28px 40px; background-color:#F6F8F7; border-top:1px solid #E4EAE6; font-family:{{ $fuente }};">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="text-align:center;">
                                        <div style="font-size:14px; line-height:20px; font-weight:700; color:{{ $tinta }};">{{ $institucion }}</div>
                                        <div style="font-size:12px; line-height:18px; color:{{ $tenue }}; padding-top:2px;">
                                            SGSI UPTex · Sistema de Gestión de Seguridad de la Información · ISO/IEC 27001:2022
                                        </div>
                                        @if ($sitioWeb !== '')
                                            <div style="font-size:12px; line-height:18px; padding-top:6px;">
                                                <a href="{{ $sitioWeb }}" target="_blank" rel="noopener" style="color:{{ $verde }}; text-decoration:none; font-weight:600;">{{ preg_replace('#^https?://#', '', rtrim($sitioWeb, '/')) }}</a>
                                            </div>
                                        @endif
                                        <table role="presentation" width="64" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:16px auto;">
                                            <tr>
                                                <td width="26" height="3" style="background-color:#0A7A3D; font-size:0; line-height:0;">&nbsp;</td>
                                                <td width="16" height="3" style="background-color:#7AB51D; font-size:0; line-height:0;">&nbsp;</td>
                                                <td width="12" height="3" style="background-color:#C8102E; font-size:0; line-height:0;">&nbsp;</td>
                                                <td width="10" height="3" style="background-color:#9AA2A8; font-size:0; line-height:0;">&nbsp;</td>
                                            </tr>
                                        </table>
                                        <p style="margin:0; font-size:11px; line-height:17px; color:#879196;">
                                            <strong>Aviso de confidencialidad:</strong> este mensaje y su contenido son confidenciales y están dirigidos exclusivamente a la persona titular de la cuenta indicada. Si lo recibiste por error, elimínalo y no compartas el enlace.
                                        </p>
                                        <p style="margin:10px 0 0 0; font-size:11px; line-height:17px; color:#879196;">
                                            Este es un mensaje automático del sistema; por favor, no respondas a este correo.<br>
                                            © {{ $anio }} {{ $institucion }}.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
