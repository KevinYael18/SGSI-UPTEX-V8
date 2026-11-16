{{--
    Encabezado institucional de las notificaciones del SGSI (plantilla Markdown
    de Laravel). Antes incrustaba el logotipo como data: URI de ~3 MB: Gmail
    recorta todo mensaje HTML de más de 102 KB (ocultando el contenido) y tanto
    Gmail como Outlook bloquean las imágenes data:. Ahora es un logotipo
    tipográfico con los colores institucionales: ligero y visible en todo cliente.
--}}
@props(['url'])
<tr>
<td class="header" style="padding: 26px 0 18px; text-align: center;">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<span style="font-family: 'Segoe UI', Arial, Helvetica, sans-serif; font-size: 30px; line-height: 32px; font-weight: 800; letter-spacing: -1px;"><span style="color: #0A7A3D;">U</span><span style="color: #7AB51D;">P</span><span style="color: #C8102E;">T</span><span style="color: #8C959B;">ex</span></span>
<br>
<span style="display: inline-block; margin-top: 8px; font-family: 'Segoe UI', Arial, Helvetica, sans-serif; font-size: 11px; line-height: 14px; font-weight: 700; letter-spacing: 1.6px; text-transform: uppercase; color: #0A7A3D;">SGSI UPTex · Universidad Politécnica de Texcoco</span>
</a>
</td>
</tr>
