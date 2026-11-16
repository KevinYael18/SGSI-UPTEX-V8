<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Valores permitidos del inventario tecnológico (clave => etiqueta en español).
 * Una sola fuente para validación (backend) y selectores (frontend vía
 * GET /api/v1/inventario/catalogos). "desconocido"/"pendiente_verificacion"
 * existen a propósito: el sistema nunca presume datos que no se conocen.
 */
final class InventarioCatalogos
{
    public const ESTADOS_OPERATIVOS = [
        'operativo' => 'Operativo',
        'mantenimiento' => 'En mantenimiento',
        'fuera_servicio' => 'Fuera de servicio',
        'baja' => 'Dado de baja',
    ];

    public const TIPOS_ALMACENAMIENTO = [
        'hdd' => 'HDD', 'ssd' => 'SSD', 'nvme' => 'SSD NVMe', 'hibrido' => 'Híbrido', 'otro' => 'Otro',
    ];

    public const ARQUITECTURAS = ['x64' => '64 bits (x64)', 'x86' => '32 bits (x86)', 'arm64' => 'ARM64'];

    public const ACTIVACION_SO = ['activado' => 'Activado', 'no_activado' => 'No activado', 'desconocido' => 'Desconocido'];

    public const SOPORTE_SO = [
        'vigente' => 'Con soporte vigente', 'extendido' => 'Soporte extendido',
        'sin_soporte' => 'Sin soporte del fabricante', 'desconocido' => 'Desconocido',
    ];

    public const INSTALACION_ANTIVIRUS = ['instalado' => 'Instalado', 'no_instalado' => 'No instalado', 'desconocido' => 'Desconocido'];

    public const PROTECCION_ANTIVIRUS = ['activa' => 'Protección activa', 'inactiva' => 'Protección inactiva', 'desconocido' => 'Desconocido'];

    public const CATEGORIAS_SOFTWARE = [
        'sistema_operativo' => 'Sistema operativo', 'ofimatica' => 'Ofimática', 'navegador' => 'Navegador web',
        'desarrollo' => 'Desarrollo de software', 'base_datos' => 'Bases de datos', 'diseno' => 'Diseño / CAD',
        'seguridad' => 'Seguridad / antivirus', 'academico' => 'Software académico especializado',
        'administrativo' => 'Software administrativo', 'virtualizacion' => 'Virtualización',
        'laboratorio' => 'Software especializado de laboratorio',
        'utileria' => 'Utilerías', 'comunicacion' => 'Comunicación', 'otro' => 'Otro',
    ];

    public const TIPOS_LICENCIA = [
        'comercial' => 'Comercial', 'educativa' => 'Educativa / campus', 'suscripcion' => 'Suscripción',
        'libre' => 'Software libre', 'gratuita' => 'Gratuita (freeware)', 'institucional' => 'Institucional',
        'prueba' => 'De prueba', 'desconocida' => 'Desconocida',
    ];

    public const ESTADOS_AUTORIZACION = [
        'autorizado' => 'Autorizado', 'no_autorizado' => 'No autorizado', 'pendiente_verificacion' => 'Pendiente de verificación',
    ];

    public const ESTADOS_LICENCIA = [
        'vigente' => 'Licencia vigente', 'vencida' => 'Licencia vencida', 'no_aplica' => 'No aplica (libre/gratuito)',
        'sin_licencia_identificada' => 'Sin licencia identificada', 'pendiente_verificacion' => 'Pendiente de verificación',
    ];

    public const TIPOS_MANTENIMIENTO = [
        'preventivo' => 'Preventivo', 'correctivo' => 'Correctivo',
        'actualizacion_software' => 'Actualización de software', 'reubicacion' => 'Reubicación', 'otro' => 'Otro',
    ];

    /**
     * Controles del Anexo A que aplican a un equipo de cómputo de usuario final
     * y que el formulario propone vincular (el usuario puede quitarlos o añadir otros).
     */
    public const CONTROLES_SUGERIDOS_COMPUTADORA = [
        'A.5.9' => 'Inventario de información y otros activos asociados',
        'A.5.10' => 'Uso aceptable de la información y otros activos asociados',
        'A.5.11' => 'Devolución de activos',
        'A.7.8' => 'Ubicación y protección de equipos',
        'A.7.13' => 'Mantenimiento de equipos',
        'A.8.1' => 'Dispositivos de usuario final',
        'A.8.7' => 'Protección contra malware',
        'A.8.8' => 'Gestión de vulnerabilidades técnicas',
        'A.8.9' => 'Gestión de la configuración',
        'A.8.19' => 'Instalación de software en sistemas operativos',
    ];

    public static function claves(array $catalogo): string
    {
        return implode(',', array_keys($catalogo));
    }

    public static function todos(): array
    {
        $aLista = fn (array $c) => array_map(fn ($k, $v) => ['value' => $k, 'label' => $v], array_keys($c), $c);

        return [
            'estados_operativos' => $aLista(self::ESTADOS_OPERATIVOS),
            'tipos_almacenamiento' => $aLista(self::TIPOS_ALMACENAMIENTO),
            'arquitecturas' => $aLista(self::ARQUITECTURAS),
            'activacion_so' => $aLista(self::ACTIVACION_SO),
            'soporte_so' => $aLista(self::SOPORTE_SO),
            'instalacion_antivirus' => $aLista(self::INSTALACION_ANTIVIRUS),
            'proteccion_antivirus' => $aLista(self::PROTECCION_ANTIVIRUS),
            'categorias_software' => $aLista(self::CATEGORIAS_SOFTWARE),
            'tipos_licencia' => $aLista(self::TIPOS_LICENCIA),
            'estados_autorizacion' => $aLista(self::ESTADOS_AUTORIZACION),
            'estados_licencia' => $aLista(self::ESTADOS_LICENCIA),
            'tipos_mantenimiento' => $aLista(self::TIPOS_MANTENIMIENTO),
            'dias_antivirus_desactualizado' => self::diasAntivirus(),
        ];
    }

    public static function diasAntivirus(): int
    {
        return max(1, (int) config('sgsi.inventario.dias_antivirus_desactualizado', 7));
    }
}
