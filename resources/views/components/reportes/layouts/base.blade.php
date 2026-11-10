<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 90px 40px 60px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2933; }
        header { position: fixed; top: -70px; left: 0; right: 0; height: 70px; border-bottom: 2px solid #0b3d91; }
        header .marca { font-size: 16px; font-weight: bold; color: #0b3d91; }
        header .subtitulo { font-size: 10px; color: #52606d; }
        footer { position: fixed; bottom: -50px; left: 0; right: 0; height: 40px; font-size: 9px; color: #7b8794; border-top: 1px solid #cbd2d9; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #0b3d91; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
        td { padding: 6px 8px; border-bottom: 1px solid #e4e7eb; font-size: 10px; }
        .badge-critico { color: #b91c1c; font-weight: bold; }
        .badge-alto { color: #c2410c; font-weight: bold; }
        .badge-medio { color: #a16207; }
        .badge-bajo { color: #15803d; }
    </style>
</head>
<body>
    <header>
        <div class="marca">SGSI UPTex — Universidad Politécnica de Texcoco</div>
        <div class="subtitulo">{{ $subtitulo }} · Generado el {{ now()->format('d/m/Y H:i') }} · ISO/IEC 27001:2022</div>
    </header>
    <footer>
        Documento de uso interno — Sistema de Gestión de Seguridad de la Información · Página <span class="pagenum"></span>
    </footer>

    {{ $slot }}
</body>
</html>