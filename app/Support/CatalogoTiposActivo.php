<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Catálogo interno de tipos específicos de activo y su ficha técnica.
 *
 * Fuente ÚNICA de verdad para:
 *  - el reconocimiento automático del tipo a partir del nombre (palabras clave
 *    ponderadas); el frontend recibe estas mismas reglas vía API y las aplica
 *    mientras el usuario escribe;
 *  - los campos que se muestran para cada tipo, agrupados por secciones;
 *  - la validación en backend de esos campos.
 *
 * No modifica la clasificación general existente (hardware, software, …):
 * cada tipo específico pertenece a UNA de esas categorías.
 *
 * Campos: [clave, etiqueta, tipo, opciones extra]. Tipos de campo:
 *   texto, textarea, entero, numero, fecha, select, ip, mac.
 * Destino: 'ficha' = se guarda en la ficha de cómputo (tabla computadoras,
 * la misma del Inventario TI); el resto va en activos.caracteristicas.
 */
final class CatalogoTiposActivo
{
    public const ESTADOS_OPERATIVOS = [
        'operativo' => 'Operativo', 'mantenimiento' => 'En mantenimiento',
        'fuera_servicio' => 'Fuera de servicio', 'baja' => 'Dado de baja',
    ];

    /** Plantillas que guardan su información técnica en la ficha de cómputo. */
    public const PLANTILLAS_CON_FICHA = ['computo', 'servidor'];

    // ------------------------------------------------------------------ tipos

    /**
     * clave => [etiqueta, categoría general, plantilla, palabras clave [frase => peso]]
     * Peso 3 = término genérico inequívoco; 2–2.5 = marca/línea de producto
     * típica; frases más específicas pesan más para ganar a las genéricas
     * (p. ej. «impresora 3d» > «impresora»).
     */
    public static function tipos(): array
    {
        return [
            // ---- Hardware: cómputo
            'computadora' => ['Computadora', 'hardware', 'computo', [
                'computadora' => 3, 'computador' => 3, 'pc' => 3, 'desktop' => 3, 'equipo de computo' => 3.5, 'cpu' => 2.5,
                'all in one' => 3, 'workstation' => 3, 'estacion de trabajo' => 3, 'optiplex' => 2.5, 'thinkcentre' => 2.5,
                'prodesk' => 2.5, 'elitedesk' => 2.5, 'imac' => 2.5, 'vostro' => 2, 'mac mini' => 3]],
            'laptop' => ['Laptop', 'hardware', 'computo', [
                'laptop' => 4, 'notebook' => 4, 'portatil' => 4, 'computadora portatil' => 4.5, 'macbook' => 4, 'chromebook' => 4,
                'probook' => 3.5, 'elitebook' => 3.5, 'thinkpad' => 3.5, 'latitude' => 3.5, 'ideapad' => 3.5, 'pavilion' => 2.5,
                'inspiron' => 2.5, 'zenbook' => 3.5, 'vivobook' => 3.5, 'aspire' => 2]],
            'servidor' => ['Servidor', 'hardware', 'servidor', [
                'servidor' => 4, 'server' => 4, 'poweredge' => 4, 'proliant' => 4, 'rack' => 2, 'blade' => 2.5,
                'nas' => 3, 'san' => 2, 'synology' => 3, 'qnap' => 3, 'hipervisor' => 3]],
            // ---- Hardware: videovigilancia
            'camara' => ['Cámara de videovigilancia', 'hardware', 'camara', [
                'camara' => 3, 'cctv' => 4, 'videovigilancia' => 4, 'camara ip' => 4, 'hikvision' => 3, 'dahua' => 3,
                'axis' => 2, 'domo' => 2.5, 'ptz' => 3, 'webcam' => 2]],
            'grabador_video' => ['Grabador de video (DVR/NVR)', 'hardware', 'red', [
                'dvr' => 4, 'nvr' => 4, 'grabador' => 3.5, 'videograbador' => 4]],
            // ---- Hardware: impresión
            'impresora' => ['Impresora', 'hardware', 'impresora', [
                'impresora' => 3, 'printer' => 3, 'multifuncional' => 3.5, 'laserjet' => 3, 'deskjet' => 3, 'officejet' => 3,
                'ecotank' => 3, 'plotter' => 3.5, 'escaner' => 2.5, 'xerox' => 2.5, 'brother' => 2, 'kyocera' => 2.5]],
            'impresora_3d' => ['Impresora 3D', 'hardware', 'impresora_3d', [
                'impresora 3d' => 5, '3d printer' => 5, 'creality' => 4, 'ender' => 3.5, 'prusa' => 4, 'ultimaker' => 4,
                'bambu' => 4, 'anycubic' => 4, 'elegoo' => 3.5, 'formlabs' => 4, 'resina' => 2]],
            // ---- Hardware: red
            'switch' => ['Switch de red', 'hardware', 'red', [
                'switch' => 4, 'conmutador' => 3, 'catalyst' => 3.5, 'switch poe' => 4.5]],
            'router' => ['Router', 'hardware', 'red', [
                'router' => 4, 'ruteador' => 4, 'enrutador' => 4, 'mikrotik' => 3, 'modem' => 3, 'gateway' => 2.5]],
            'punto_acceso' => ['Punto de acceso inalámbrico', 'hardware', 'red', [
                'access point' => 4.5, 'punto de acceso' => 4.5, 'ap' => 2, 'unifi' => 3, 'aruba' => 3, 'antena' => 2.5, 'repetidor' => 3]],
            'firewall' => ['Firewall', 'hardware', 'red', [
                'firewall' => 4, 'fortigate' => 4, 'sonicwall' => 4, 'pfsense' => 3.5, 'cortafuegos' => 4, 'utm' => 3]],
            // ---- Hardware: Mecatrónica y automatización
            'plc' => ['Controlador industrial (PLC)', 'hardware', 'industrial', [
                'plc' => 4, 'controlador logico' => 4.5, 'controlador industrial' => 4.5, 's7' => 2.5, 'simatic' => 3.5,
                'allen bradley' => 3.5, 'controllogix' => 3.5, 'micrologix' => 3.5, 'logo' => 1.5]],
            'cnc' => ['Maquinaria CNC', 'hardware', 'industrial', [
                'cnc' => 4.5, 'torno' => 3, 'fresadora' => 3.5, 'router cnc' => 5, 'cortadora laser' => 3.5, 'centro de maquinado' => 4]],
            'robot' => ['Brazo robótico / robot industrial', 'hardware', 'industrial', [
                'brazo robotico' => 5, 'robot' => 3.5, 'robotico' => 3, 'kuka' => 3.5, 'fanuc' => 3.5, 'manipulador' => 3, 'cobot' => 4]],
            'automatizacion' => ['Equipo de automatización', 'hardware', 'industrial', [
                'automatizacion' => 3.5, 'variador' => 3.5, 'servomotor' => 3.5, 'hmi' => 3.5, 'banda transportadora' => 3.5,
                'neumatica' => 3, 'modulo de entradas' => 3, 'sensor' => 3, 'sensor industrial' => 4, 'actuador' => 3]],
            'instrumento_laboratorio' => ['Instrumento de laboratorio', 'hardware', 'instrumento', [
                'osciloscopio' => 4.5, 'fuente de alimentacion' => 4.5, 'fuente de poder' => 3.5, 'multimetro' => 4.5,
                'generador de funciones' => 4.5, 'estacion de soldadura' => 4.5, 'cautin' => 4, 'analizador' => 3, 'protoboard' => 2.5,
                'microscopio' => 3.5, 'balanza' => 3]],
            // ---- Hardware: otros equipos físicos
            'climatizacion' => ['Equipo de climatización', 'hardware', 'climatizacion', [
                'aire acondicionado' => 5, 'minisplit' => 4.5, 'mini split' => 4.5, 'clima' => 2.5, 'climatizacion' => 4, 'enfriador' => 3]],
            'proyector' => ['Proyector', 'hardware', 'proyector', [
                'proyector' => 4.5, 'videoproyector' => 4.5, 'canon proyector' => 4, 'powerlite' => 3.5]],
            'pantalla' => ['Pantalla / monitor', 'hardware', 'pantalla', [
                'pantalla' => 3, 'monitor' => 3, 'television' => 3, 'tv' => 2.5, 'smart tv' => 3.5, 'pizarron interactivo' => 4]],
            'audiovisual' => ['Equipo audiovisual', 'hardware', 'general', [
                'bocina' => 3.5, 'microfono' => 3.5, 'consola de audio' => 4, 'equipo de sonido' => 4, 'amplificador' => 3.5, 'videoconferencia' => 3.5]],
            'electrico' => ['Equipo eléctrico', 'hardware', 'general', [
                'ups' => 4, 'no break' => 4, 'nobreak' => 4, 'regulador' => 3.5, 'planta de luz' => 4, 'planta electrica' => 4, 'tablero electrico' => 4]],

            // ---- Software
            'sistema_operativo' => ['Sistema operativo', 'software', 'software', [
                'windows' => 4, 'ubuntu' => 4, 'linux' => 4, 'macos' => 4, 'debian' => 4, 'red hat' => 4, 'centos' => 4,
                'fedora' => 4, 'android' => 3, 'sistema operativo' => 5, 'windows server' => 5, 'rocky linux' => 4.5]],
            'antivirus' => ['Antivirus', 'software', 'software', [
                'antivirus' => 5, 'eset' => 4, 'nod32' => 4, 'kaspersky' => 4, 'norton' => 4, 'mcafee' => 4, 'avast' => 4,
                'bitdefender' => 4, 'defender' => 3.5, 'sophos' => 4, 'malwarebytes' => 4, 'antimalware' => 4.5]],
            'productividad' => ['Software de productividad', 'software', 'software', [
                'office' => 4, 'microsoft 365' => 4.5, 'excel' => 3.5, 'word' => 3, 'powerpoint' => 3.5, 'libreoffice' => 4,
                'google workspace' => 4, 'acrobat' => 3.5, 'teams' => 3, 'outlook' => 3]],
            'desarrollo' => ['Software de desarrollo', 'software', 'software', [
                'visual studio' => 4, 'vs code' => 4, 'visual studio code' => 4.5, 'xampp' => 4, 'python' => 3.5, 'java' => 3,
                'jdk' => 3.5, 'node' => 3, 'nodejs' => 3.5, 'git' => 3, 'android studio' => 4.5, 'netbeans' => 4, 'eclipse' => 3.5,
                'intellij' => 4, 'pycharm' => 4, 'postman' => 3.5, 'php' => 3]],
            'base_datos' => ['Sistema de base de datos', 'software', 'software', [
                'mysql' => 4, 'postgresql' => 4, 'postgres' => 4, 'sql server' => 4.5, 'oracle database' => 4.5, 'mariadb' => 4,
                'mongodb' => 4, 'sqlite' => 3.5, 'workbench' => 2.5]],
            'academico' => ['Software académico', 'software', 'software', [
                'moodle' => 4, 'minitab' => 4, 'spss' => 4, 'geogebra' => 4, 'mathematica' => 4, 'software academico' => 5]],
            'administrativo' => ['Software administrativo', 'software', 'software', [
                'contpaq' => 4, 'contpaqi' => 4, 'aspel' => 4, 'nomina' => 3, 'erp' => 3.5, 'sistema escolar' => 4,
                'control escolar' => 4, 'facturacion' => 3.5, 'sap' => 3]],
            'diseno' => ['Software de diseño', 'software', 'software', [
                'autocad' => 4, 'solidworks' => 4, 'photoshop' => 4, 'illustrator' => 4, 'coreldraw' => 4, 'sketchup' => 4,
                'blender' => 4, 'fusion 360' => 4, 'inventor' => 3, 'revit' => 4, 'adobe' => 2.5, 'cura' => 3]],
            'virtualizacion' => ['Software de virtualización', 'software', 'software', [
                'vmware' => 4, 'virtualbox' => 4, 'hyper-v' => 4, 'hyper v' => 4, 'proxmox' => 4, 'docker' => 4, 'esxi' => 4]],
            'seguridad' => ['Software de seguridad', 'software', 'software', [
                'vpn' => 3.5, 'wireshark' => 4, 'nmap' => 4, 'kali' => 4, 'burp' => 4, 'gestor de contrasenas' => 4.5, 'keepass' => 4,
                'firewall de software' => 4.5, 'cifrado' => 3]],
            'laboratorio' => ['Software especializado de laboratorio', 'software', 'software', [
                'labview' => 4.5, 'multisim' => 4.5, 'proteus' => 4.5, 'tia portal' => 4.5, 'step 7' => 4, 'rslogix' => 4.5,
                'matlab' => 4, 'simulink' => 4, 'arduino' => 3.5, 'factory io' => 4.5, 'codesys' => 4.5, 'mastercam' => 4.5]],
        ];
    }

    // -------------------------------------------------------------- plantillas

    private static function f(string $clave, string $etiqueta, string $tipo = 'texto', array $extra = []): array
    {
        return ['clave' => $clave, 'etiqueta' => $etiqueta, 'tipo' => $tipo] + $extra;
    }

    private static function opciones(array $mapa): array
    {
        return array_map(fn ($k, $v) => ['value' => $k, 'label' => $v], array_keys($mapa), $mapa);
    }

    private static function identificacion(bool $ficha = false): array
    {
        $d = $ficha ? ['destino' => 'ficha'] : [];

        return ['titulo' => 'Identificación', 'campos' => [
            self::f('fabricante', 'Fabricante', 'texto', ['max' => 80] + $d),
            self::f('marca', 'Marca', 'texto', ['max' => 80]),
            self::f('modelo', 'Modelo', 'texto', ['max' => 100] + $d),
            self::f('numero_serie', 'Número de serie', 'texto', ['max' => 100, 'unico' => true] + $d),
            self::f('numero_inventario', 'No. de inventario institucional', 'texto', ['max' => 60, 'unico' => true] + $d),
        ]];
    }

    private static function estadoYMantenimiento(bool $ficha = false): array
    {
        $campos = [self::f('estado_operativo', 'Estado operativo', 'select', ['opciones' => self::opciones(self::ESTADOS_OPERATIVOS)] + ($ficha ? ['destino' => 'ficha'] : []))];
        if (!$ficha) {
            $campos[] = self::f('fecha_ultimo_mantenimiento', 'Último mantenimiento', 'fecha');
            $campos[] = self::f('fecha_proximo_mantenimiento', 'Próximo mantenimiento', 'fecha', ['futura' => true]);
            $campos[] = self::f('proveedor_mantenimiento', 'Responsable / proveedor del mantenimiento', 'texto', ['max' => 150]);
        }

        return ['titulo' => $ficha ? 'Estado' : 'Estado y mantenimiento', 'campos' => $campos];
    }

    private static function conectividad(): array
    {
        return self::opciones(['sin_red' => 'Sin conexión de red', 'ethernet' => 'Ethernet (cable)', 'wifi' => 'Wi-Fi',
            'usb' => 'USB', 'serial' => 'Serial (RS-232/485)', 'bus_campo' => 'Bus de campo (Profibus, Modbus RTU…)', 'otra' => 'Otra']);
    }

    private static function soloConRed(): array
    {
        return ['mostrar_si' => ['conectividad' => ['ethernet', 'wifi']]];
    }

    public static function plantillas(): array
    {
        $licencias = self::opciones(['libre' => 'Software libre', 'propietaria' => 'Propietaria / comercial', 'institucional' => 'Institucional / campus',
            'educativa' => 'Educativa', 'suscripcion' => 'Suscripción', 'gratuita' => 'Gratuita (freeware)', 'prueba' => 'De prueba', 'desconocida' => 'Desconocida']);

        return [
            'computo' => [
                self::identificacion(true) + [],
                ['titulo' => 'Equipo', 'campos' => [
                    self::f('hostname', 'Nombre del equipo (hostname)', 'texto', ['max' => 100, 'destino' => 'ficha']),
                ]],
                ['titulo' => 'Características técnicas', 'campos' => [
                    self::f('procesador', 'Procesador', 'texto', ['max' => 150, 'destino' => 'ficha']),
                    self::f('generacion_procesador', 'Generación del procesador', 'texto', ['max' => 40]),
                    self::f('ram_gb', 'Memoria RAM (GB)', 'entero', ['min' => 1, 'maxv' => 4096, 'destino' => 'ficha']),
                    self::f('tipo_ram', 'Tipo de memoria RAM', 'select', ['opciones' => self::opciones(['ddr3' => 'DDR3', 'ddr4' => 'DDR4', 'ddr5' => 'DDR5', 'lpddr' => 'LPDDR', 'otra' => 'Otra'])]),
                    self::f('almacenamiento_gb', 'Capacidad de almacenamiento (GB)', 'entero', ['min' => 1, 'maxv' => 1048576, 'destino' => 'ficha']),
                    self::f('tipo_almacenamiento', 'Tipo de almacenamiento', 'select', ['opciones' => self::opciones(InventarioCatalogos::TIPOS_ALMACENAMIENTO), 'destino' => 'ficha']),
                    self::f('tarjeta_grafica', 'Tarjeta gráfica', 'texto', ['max' => 150, 'destino' => 'ficha']),
                    self::f('so_nombre', 'Sistema operativo', 'texto', ['max' => 80, 'destino' => 'ficha']),
                    self::f('so_version', 'Versión del sistema operativo', 'texto', ['max' => 60, 'destino' => 'ficha']),
                    self::f('so_arquitectura', 'Arquitectura', 'select', ['opciones' => self::opciones(InventarioCatalogos::ARQUITECTURAS), 'destino' => 'ficha']),
                ]],
                ['titulo' => 'Red', 'campos' => [
                    self::f('direccion_ip', 'Dirección IP', 'ip', ['destino' => 'ficha']),
                    self::f('direccion_mac', 'Dirección MAC', 'mac', ['destino' => 'ficha', 'unico' => true]),
                    self::f('conexion_red', 'Conexión', 'select', ['opciones' => self::opciones(['ethernet' => 'Ethernet', 'wifi' => 'Wi-Fi', 'ambas' => 'Ethernet y Wi-Fi'])]),
                    self::f('dominio_red', 'Nombre de red o dominio', 'texto', ['max' => 120]),
                ]],
                ['titulo' => 'Seguridad', 'campos' => [
                    self::f('antivirus_nombre', 'Antivirus instalado', 'texto', ['max' => 100, 'destino' => 'ficha']),
                    self::f('antivirus_version', 'Versión del antivirus', 'texto', ['max' => 60, 'destino' => 'ficha']),
                    self::f('antivirus_proteccion', 'Estado de protección', 'select', ['opciones' => self::opciones(InventarioCatalogos::PROTECCION_ANTIVIRUS), 'destino' => 'ficha']),
                    self::f('estado_actualizacion_antivirus', 'Estado de actualización', 'select', ['opciones' => self::opciones(['al_dia' => 'Al día', 'pendiente' => 'Pendiente', 'desconocido' => 'Desconocido'])]),
                    self::f('antivirus_ultima_actualizacion', 'Fecha de última revisión', 'fecha', ['destino' => 'ficha']),
                ]],
                self::estadoYMantenimiento(true),
            ],
            'servidor' => [
                self::identificacion(true),
                ['titulo' => 'Configuración', 'campos' => [
                    self::f('hostname', 'Hostname', 'texto', ['max' => 100, 'destino' => 'ficha']),
                    self::f('procesador', 'Procesador', 'texto', ['max' => 150, 'destino' => 'ficha']),
                    self::f('ram_gb', 'Memoria RAM (GB)', 'entero', ['min' => 1, 'maxv' => 65536, 'destino' => 'ficha']),
                    self::f('almacenamiento_gb', 'Capacidad de almacenamiento (GB)', 'entero', ['min' => 1, 'maxv' => 10485760, 'destino' => 'ficha']),
                    self::f('raid', 'Configuración RAID', 'select', ['opciones' => self::opciones(['ninguno' => 'Sin RAID', 'raid0' => 'RAID 0', 'raid1' => 'RAID 1', 'raid5' => 'RAID 5', 'raid6' => 'RAID 6', 'raid10' => 'RAID 10', 'otro' => 'Otro'])]),
                    self::f('so_nombre', 'Sistema operativo', 'texto', ['max' => 80, 'destino' => 'ficha']),
                    self::f('so_version', 'Versión', 'texto', ['max' => 60, 'destino' => 'ficha']),
                    self::f('virtualizacion', 'Virtualización', 'select', ['opciones' => self::opciones(['ninguna' => 'Servidor físico sin virtualización', 'anfitrion' => 'Anfitrión de máquinas virtuales', 'virtual' => 'Es una máquina virtual'])]),
                    self::f('plataforma_virtualizacion', 'Plataforma de virtualización', 'texto', ['max' => 80, 'mostrar_si' => ['virtualizacion' => ['anfitrion', 'virtual']]]),
                    self::f('servicios_alojados', 'Servicios alojados', 'textarea', ['max' => 1000, 'ayuda' => 'Ej. sitio web institucional, base de datos de control escolar…']),
                ]],
                ['titulo' => 'Red', 'campos' => [
                    self::f('direccion_ip', 'Dirección IP', 'ip', ['destino' => 'ficha']),
                    self::f('direccion_mac', 'Dirección MAC', 'mac', ['destino' => 'ficha', 'unico' => true]),
                ]],
                self::estadoYMantenimiento(true),
            ],
            'camara' => [
                self::identificacion(),
                ['titulo' => 'Características', 'campos' => [
                    self::f('tipo_camara', 'Tipo de cámara', 'select', ['opciones' => self::opciones(['domo' => 'Domo', 'bala' => 'Bala (bullet)', 'ptz' => 'PTZ (motorizada)', 'fisheye' => 'Ojo de pez (360°)', 'oculta' => 'Oculta / mini', 'otra' => 'Otra'])]),
                    self::f('resolucion', 'Resolución', 'texto', ['max' => 40, 'ayuda' => 'Ej. 4 MP, 1080p']),
                    self::f('firmware', 'Firmware', 'texto', ['max' => 60]),
                    self::f('grabador_asociado', 'Grabador asociado (DVR/NVR)', 'texto', ['max' => 120]),
                ]],
                ['titulo' => 'Red', 'campos' => [
                    self::f('direccion_ip', 'Dirección IP', 'ip'),
                    self::f('direccion_mac', 'Dirección MAC', 'mac', ['unico' => true]),
                ]],
                ['titulo' => 'Estado', 'campos' => [self::f('estado_operativo', 'Estado operativo', 'select', ['opciones' => self::opciones(self::ESTADOS_OPERATIVOS)])]],
            ],
            'impresora' => [
                self::identificacion(),
                ['titulo' => 'Características', 'campos' => [
                    self::f('tipo_impresora', 'Tipo de impresora', 'select', ['opciones' => self::opciones(['personal' => 'Personal', 'departamental' => 'Departamental', 'multifuncional' => 'Multifuncional', 'plotter' => 'Plotter / gran formato', 'etiquetas' => 'Etiquetas / tickets'])]),
                    self::f('tecnologia_impresion', 'Tecnología de impresión', 'select', ['opciones' => self::opciones(['laser' => 'Láser', 'inyeccion' => 'Inyección de tinta', 'tanque' => 'Tanque de tinta', 'termica' => 'Térmica', 'matricial' => 'Matricial', 'otra' => 'Otra'])]),
                    self::f('conectividad', 'Conectividad', 'select', ['opciones' => self::conectividad()]),
                ]],
                ['titulo' => 'Red', 'campos' => [
                    self::f('direccion_ip', 'Dirección IP', 'ip', self::soloConRed()),
                    self::f('direccion_mac', 'Dirección MAC', 'mac', ['unico' => true] + self::soloConRed()),
                ]],
                self::estadoYMantenimiento(),
            ],
            'impresora_3d' => [
                self::identificacion(),
                ['titulo' => 'Características', 'campos' => [
                    self::f('tecnologia_impresion', 'Tecnología de impresión', 'select', ['opciones' => self::opciones(['fdm' => 'FDM (filamento)', 'sla' => 'SLA (resina)', 'dlp' => 'DLP', 'sls' => 'SLS (polvo)', 'otra' => 'Otra'])]),
                    self::f('volumen_impresion', 'Volumen de impresión', 'texto', ['max' => 60, 'ayuda' => 'Ej. 220 × 220 × 250 mm']),
                    self::f('materiales', 'Materiales compatibles', 'texto', ['max' => 200, 'ayuda' => 'Ej. PLA, PETG, ABS']),
                    self::f('firmware', 'Firmware', 'texto', ['max' => 60]),
                    self::f('conectividad', 'Conectividad', 'select', ['opciones' => self::conectividad()]),
                    self::f('direccion_ip', 'Dirección IP', 'ip', self::soloConRed()),
                ]],
                self::estadoYMantenimiento(),
            ],
            'red' => [
                self::identificacion(),
                ['titulo' => 'Configuración de red', 'campos' => [
                    self::f('direccion_ip', 'Dirección IP de administración', 'ip'),
                    self::f('direccion_mac', 'Dirección MAC', 'mac', ['unico' => true]),
                    self::f('firmware', 'Firmware', 'texto', ['max' => 60]),
                    self::f('numero_puertos', 'Número de puertos', 'entero', ['min' => 0, 'maxv' => 1024]),
                    self::f('velocidad_puertos', 'Velocidad de puertos', 'texto', ['max' => 40, 'ayuda' => 'Ej. 1 Gbps, 10 Gbps SFP+']),
                    self::f('vlan_segmento', 'VLAN o segmento de red', 'texto', ['max' => 80]),
                ]],
                ['titulo' => 'Estado', 'campos' => [self::f('estado_operativo', 'Estado operativo', 'select', ['opciones' => self::opciones(self::ESTADOS_OPERATIVOS)])]],
            ],
            'industrial' => [
                self::identificacion(),
                ['titulo' => 'Características', 'campos' => [
                    self::f('tipo_equipo', 'Tipo de equipo', 'texto', ['max' => 80, 'ayuda' => 'Se propone según el nombre; puedes precisarlo.']),
                    self::f('firmware', 'Firmware / versión del software', 'texto', ['max' => 80]),
                    self::f('interfaz_comunicacion', 'Interfaz de comunicación', 'texto', ['max' => 100, 'ayuda' => 'Ej. Ethernet, RS-485, USB']),
                    self::f('protocolos', 'Protocolos de comunicación', 'texto', ['max' => 150, 'ayuda' => 'Ej. Modbus TCP, Profinet, EtherNet/IP']),
                    self::f('conectividad', 'Conectividad', 'select', ['opciones' => self::conectividad()]),
                    self::f('direccion_ip', 'Dirección IP', 'ip', self::soloConRed()),
                ]],
                self::estadoYMantenimiento(),
            ],
            'instrumento' => [
                self::identificacion(),
                ['titulo' => 'Características', 'campos' => [
                    self::f('tipo_equipo', 'Tipo de equipo', 'texto', ['max' => 80]),
                    self::f('especificaciones', 'Especificaciones principales', 'textarea', ['max' => 500, 'ayuda' => 'Ej. ancho de banda, rangos, potencia']),
                    self::f('fecha_calibracion', 'Última calibración', 'fecha'),
                    self::f('fecha_proxima_calibracion', 'Próxima calibración', 'fecha', ['futura' => true]),
                ]],
                self::estadoYMantenimiento(),
            ],
            'climatizacion' => [
                self::identificacion(),
                ['titulo' => 'Características', 'campos' => [
                    self::f('tipo_equipo', 'Tipo', 'select', ['opciones' => self::opciones(['minisplit' => 'Minisplit', 'ventana' => 'De ventana', 'central' => 'Central', 'precision' => 'Precisión (centro de datos)', 'portatil' => 'Portátil', 'otro' => 'Otro'])]),
                    self::f('capacidad', 'Capacidad', 'texto', ['max' => 40, 'ayuda' => 'Ej. 12 000 BTU, 1 tonelada']),
                    self::f('area_atendida', 'Área que climatiza', 'texto', ['max' => 120]),
                ]],
                self::estadoYMantenimiento(),
            ],
            'proyector' => [
                self::identificacion(),
                ['titulo' => 'Características', 'campos' => [
                    self::f('resolucion', 'Resolución', 'texto', ['max' => 40]),
                    self::f('lumenes', 'Luminosidad (lúmenes)', 'entero', ['min' => 0, 'maxv' => 100000]),
                    self::f('horas_lampara', 'Horas de uso de la lámpara', 'entero', ['min' => 0, 'maxv' => 200000]),
                    self::f('entradas', 'Entradas de video', 'texto', ['max' => 120, 'ayuda' => 'Ej. HDMI, VGA']),
                ]],
                self::estadoYMantenimiento(),
            ],
            'pantalla' => [
                self::identificacion(),
                ['titulo' => 'Características', 'campos' => [
                    self::f('tamano_pulgadas', 'Tamaño (pulgadas)', 'numero', ['min' => 1, 'maxv' => 500]),
                    self::f('resolucion', 'Resolución', 'texto', ['max' => 40]),
                    self::f('entradas', 'Entradas de video', 'texto', ['max' => 120]),
                ]],
                self::estadoYMantenimiento(),
            ],
            'general' => [
                self::identificacion(),
                ['titulo' => 'Características', 'campos' => [
                    self::f('descripcion_tecnica', 'Descripción técnica', 'textarea', ['max' => 1000]),
                ]],
                self::estadoYMantenimiento(),
            ],
            'software' => [
                ['titulo' => 'Identificación', 'campos' => [
                    self::f('fabricante', 'Fabricante o desarrollador', 'texto', ['max' => 120]),
                    self::f('version', 'Versión', 'texto', ['max' => 60]),
                    self::f('edicion', 'Edición', 'texto', ['max' => 80, 'solo_subtipos' => ['sistema_operativo', 'productividad']]),
                    self::f('descripcion', 'Descripción', 'textarea', ['max' => 1000]),
                ]],
                ['titulo' => 'Compatibilidad', 'campos' => [
                    self::f('so_compatibles', 'Sistemas operativos compatibles', 'texto', ['max' => 200, 'excepto_subtipos' => ['sistema_operativo']]),
                    self::f('arquitectura', 'Arquitectura compatible', 'select', ['opciones' => self::opciones(['x64' => '64 bits (x64)', 'x86' => '32 bits (x86)', 'arm64' => 'ARM64', 'multiplataforma' => 'Varias / multiplataforma'])]),
                    self::f('requisitos', 'Requisitos técnicos', 'textarea', ['max' => 500]),
                    self::f('ciclo_soporte', 'Soporte del fabricante', 'select', ['opciones' => self::opciones(InventarioCatalogos::SOPORTE_SO), 'solo_subtipos' => ['sistema_operativo']]),
                ]],
                ['titulo' => 'Licenciamiento', 'campos' => [
                    self::f('tipo_licencia', 'Tipo de licencia', 'select', ['opciones' => $licencias]),
                    self::f('referencia_licencia', 'Número de contrato o referencia de la licencia', 'texto', ['max' => 100,
                        'ayuda' => 'Solo una referencia (contrato, orden de compra). Nunca escribas aquí la clave del producto ni contraseñas.']),
                    self::f('cantidad_licencias', 'Licencias adquiridas', 'entero', ['min' => 0, 'maxv' => 1000000]),
                    self::f('fecha_adquisicion', 'Fecha de adquisición', 'fecha'),
                    self::f('fecha_expiracion', 'Fecha de expiración', 'fecha', ['futura' => true]),
                    self::f('estado_licencia', 'Estado de la licencia', 'select', ['opciones' => self::opciones(InventarioCatalogos::ESTADOS_LICENCIA)]),
                ]],
                ['titulo' => 'Administración', 'campos' => [
                    self::f('estado_actualizacion', 'Estado de actualización', 'select', ['opciones' => self::opciones(['actualizado' => 'Actualizado', 'requiere_actualizacion' => 'Requiere actualización', 'desconocido' => 'Desconocido'])]),
                    self::f('frecuencia_actualizacion', 'Actualización de firmas', 'texto', ['max' => 80, 'solo_subtipos' => ['antivirus']]),
                    self::f('observaciones', 'Observaciones', 'textarea', ['max' => 1000]),
                ]],
            ],
        ];
    }

    /** Controles del Anexo A sugeridos por plantilla (solo sugerencia: NO implica cumplimiento). */
    public static function controlesSugeridos(string $plantilla): array
    {
        return match ($plantilla) {
            'computo' => ['A.5.9', 'A.5.10', 'A.5.11', 'A.7.8', 'A.7.13', 'A.8.1', 'A.8.7', 'A.8.8', 'A.8.9', 'A.8.19'],
            'servidor' => ['A.5.9', 'A.7.8', 'A.7.13', 'A.8.7', 'A.8.8', 'A.8.9'],
            'camara', 'red', 'impresora', 'impresora_3d', 'industrial' => ['A.5.9', 'A.7.8', 'A.7.13', 'A.8.8', 'A.8.9'],
            'software' => ['A.5.9', 'A.8.19', 'A.8.8'],
            default => ['A.5.9', 'A.7.8', 'A.7.13'],
        };
    }

    // ------------------------------------------------------------ consultas

    public static function existe(string $subtipo): bool
    {
        return isset(self::tipos()[$subtipo]);
    }

    public static function categoriaDe(string $subtipo): ?string
    {
        return self::tipos()[$subtipo][1] ?? null;
    }

    public static function etiqueta(?string $subtipo): ?string
    {
        return $subtipo ? (self::tipos()[$subtipo][0] ?? null) : null;
    }

    public static function plantillaDe(string $subtipo): ?string
    {
        return self::tipos()[$subtipo][2] ?? null;
    }

    /** Campos aplicables a un subtipo (respeta solo_subtipos / excepto_subtipos). */
    public static function campos(string $subtipo): array
    {
        $plantilla = self::plantillaDe($subtipo);
        if ($plantilla === null) {
            return [];
        }
        $campos = [];
        foreach (self::plantillas()[$plantilla] as $seccion) {
            foreach ($seccion['campos'] as $c) {
                if (isset($c['solo_subtipos']) && !in_array($subtipo, $c['solo_subtipos'], true)) {
                    continue;
                }
                if (isset($c['excepto_subtipos']) && in_array($subtipo, $c['excepto_subtipos'], true)) {
                    continue;
                }
                $campos[$c['clave']] = $c;
            }
        }

        return $campos;
    }

    public static function normalizar(string $texto): string
    {
        $t = mb_strtolower($texto, 'UTF-8');
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $t = preg_replace('/[^a-z0-9]+/', ' ', $t);

        return trim(preg_replace('/\s+/', ' ', $t));
    }

    /**
     * Reconoce el tipo específico a partir del nombre, dentro de la categoría
     * general elegida. Devuelve el mejor candidato y la confianza:
     *  - alta:  puntaje ≥ 3 y ventaja ≥ 1 sobre el segundo → se aplica solo;
     *  - media: hay coincidencias pero ambiguas o débiles → se pide confirmar;
     *  - ninguna: no se reconoce → registro general (nunca bloquea).
     * Mismo algoritmo que frontend/src/composables/useReconocimientoActivo.js.
     *
     * @return array{subtipo: ?string, confianza: string, candidatos: array<int, array{subtipo: string, etiqueta: string, puntaje: float}>}
     */
    public static function reconocer(?string $categoria, string $nombre): array
    {
        $texto = ' ' . self::normalizar($nombre) . ' ';
        $puntajes = [];
        foreach (self::tipos() as $clave => [$etiqueta, $cat, , $palabras]) {
            if ($categoria !== null && $categoria !== '' && $cat !== $categoria) {
                continue;
            }
            $max = 0.0;
            $coincidencias = 0;
            foreach ($palabras as $frase => $peso) {
                if (str_contains($texto, ' ' . self::normalizar($frase) . ' ')) {
                    $max = max($max, (float) $peso);
                    $coincidencias++;
                }
            }
            if ($max > 0) {
                $puntajes[] = ['subtipo' => $clave, 'etiqueta' => $etiqueta, 'puntaje' => round($max + 0.1 * ($coincidencias - 1), 2)];
            }
        }
        usort($puntajes, fn ($a, $b) => $b['puntaje'] <=> $a['puntaje']);
        $candidatos = array_slice($puntajes, 0, 3);

        if ($candidatos === []) {
            return ['subtipo' => null, 'confianza' => 'ninguna', 'candidatos' => []];
        }
        $primero = $candidatos[0]['puntaje'];
        $segundo = $candidatos[1]['puntaje'] ?? 0;
        $confianza = ($primero >= 3 && $primero - $segundo >= 1) ? 'alta' : 'media';

        return ['subtipo' => $candidatos[0]['subtipo'], 'confianza' => $confianza, 'candidatos' => $candidatos];
    }

    /** Definición completa para el frontend (tipos, reglas y plantillas). */
    public static function paraFrontend(): array
    {
        $tipos = [];
        foreach (self::tipos() as $clave => [$etiqueta, $cat, $plantilla, $palabras]) {
            $tipos[] = [
                'clave' => $clave, 'etiqueta' => $etiqueta, 'categoria' => $cat, 'plantilla' => $plantilla,
                'palabras' => $palabras, 'usa_ficha_computo' => in_array($plantilla, self::PLANTILLAS_CON_FICHA, true),
                'controles_sugeridos' => self::controlesSugeridos($plantilla),
            ];
        }

        return ['tipos' => $tipos, 'plantillas' => self::plantillas()];
    }
}
