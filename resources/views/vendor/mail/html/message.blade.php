{{-- Layout de las notificaciones Markdown del SGSI, con pie institucional en español. --}}
<x-mail::layout>
{{-- Encabezado --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Cuerpo --}}
{!! $slot !!}

{{-- Subcopia --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Pie --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('sgsi.correo.institucion', 'Universidad Politécnica de Texcoco') }} · SGSI UPTex.
Mensaje automático y confidencial del Sistema de Gestión de Seguridad de la Información; por favor, no respondas a este correo.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
