{{-- Plantilla genérica de notificaciones del SGSI (versión en español de la de Laravel). --}}
<x-mail::message>
{{-- Saludo --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# Atención
@else
# Hola
@endif
@endif

{{-- Líneas de introducción --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Botón de acción --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Líneas finales --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Despedida --}}
@if (! empty($salutation))
{{ $salutation }}
@else
Atentamente,<br>
SGSI UPTex
@endif

{{-- Subcopia --}}
@isset($actionText)
<x-slot:subcopy>
Si el botón «{{ $actionText }}» no funciona, copia y pega el siguiente enlace en tu navegador:
<span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
