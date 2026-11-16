{{-- Versión de TEXTO PLANO del correo de recuperación. Se usa {!! !!} porque
     aquí no hay HTML: escapar convertiría el "&" del enlace en "&amp;" y lo rompería. --}}
@php($primerNombre = trim(explode(' ', trim($nombre))[0] ?? ''))
{!! mb_strtoupper($institucion, 'UTF-8') !!}
SGSI UPTex · Sistema de Gestión de Seguridad de la Información
============================================================

RESTABLECIMIENTO DE CONTRASEÑA

Hola{!! $primerNombre !== '' ? ', ' . $primerNombre : '' !!}.

Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en SGSI UPTex ({!! $correo !!}).

Para establecer una nueva contraseña, abre el siguiente enlace en tu navegador:

{!! $url !!}

- Vigencia: {!! $vigencia !!}. El enlace solo puede utilizarse una vez.
- Solicitud recibida el {!! $solicitadoEn !!}.

¿NO SOLICITASTE ESTE CAMBIO?
Ignora este mensaje: tu contraseña actual seguirá funcionando y nadie podrá cambiarla sin este enlace. Si recibes solicitudes que no reconoces, avisa al Responsable del SGSI o al área de Tecnologías de la Información de la universidad.

IMPORTANTE: nadie de la {!! $institucion !!} te pedirá tu contraseña por correo electrónico, teléfono ni mensaje. Nunca la compartas.

------------------------------------------------------------
{!! $institucion !!}
SGSI UPTex · ISO/IEC 27001:2022
@if ($sitioWeb !== '')
{!! $sitioWeb !!}
@endif

Aviso de confidencialidad: este mensaje es confidencial y está dirigido exclusivamente a la persona titular de la cuenta indicada. Si lo recibiste por error, elimínalo y no compartas el enlace.
Este es un mensaje automático; por favor, no respondas a este correo.
