<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClausulaIso;
use App\Models\ControlIso;
use Illuminate\Database\Seeder;

/**
 * Carga las cláusulas 4-10 y los 93 controles reales del Anexo A de
 * ISO/IEC 27001:2022 (37 organizacionales + 8 de personas + 14 físicos +
 * 34 tecnológicos), tal como se aprobó en Fase 1.5 §4. Es contenido
 * público de la norma (códigos y nombres de control), no información
 * propietaria de la institución.
 */
class ClausulasControlesIsoSeeder extends Seeder
{
    public function run(): void
    {
        $clausulas = [
            '4' => 'Contexto de la organización',
            '5' => 'Liderazgo',
            '6' => 'Planificación',
            '7' => 'Soporte',
            '8' => 'Operación',
            '9' => 'Evaluación del desempeño',
            '10' => 'Mejora',
        ];

        foreach ($clausulas as $numero => $nombre) {
            ClausulaIso::query()->firstOrCreate(['numero' => $numero], ['nombre' => $nombre]);
        }

        foreach ($this->controlesOrganizacionales() as $codigo => $nombre) {
            $this->crearControl($codigo, $nombre, 'organizacional');
        }
        foreach ($this->controlesPersonas() as $codigo => $nombre) {
            $this->crearControl($codigo, $nombre, 'personas');
        }
        foreach ($this->controlesFisicos() as $codigo => $nombre) {
            $this->crearControl($codigo, $nombre, 'fisico');
        }
        foreach ($this->controlesTecnologicos() as $codigo => $nombre) {
            $this->crearControl($codigo, $nombre, 'tecnologico');
        }
    }

    private function crearControl(string $codigo, string $nombre, string $categoria): void
    {
        ControlIso::query()->firstOrCreate(
            ['codigo' => $codigo],
            ['nombre' => $nombre, 'categoria' => $categoria, 'estado_implementacion' => 'no_implementado']
        );
    }

    /** 37 controles organizacionales (A.5) */
    private function controlesOrganizacionales(): array
    {
        return [
            'A.5.1' => 'Políticas de seguridad de la información',
            'A.5.2' => 'Roles y responsabilidades de seguridad de la información',
            'A.5.3' => 'Segregación de funciones',
            'A.5.4' => 'Responsabilidades de la dirección',
            'A.5.5' => 'Contacto con autoridades',
            'A.5.6' => 'Contacto con grupos de interés especial',
            'A.5.7' => 'Inteligencia de amenazas',
            'A.5.8' => 'Seguridad de la información en gestión de proyectos',
            'A.5.9' => 'Inventario de información y otros activos asociados',
            'A.5.10' => 'Uso aceptable de la información y otros activos asociados',
            'A.5.11' => 'Devolución de activos',
            'A.5.12' => 'Clasificación de la información',
            'A.5.13' => 'Etiquetado de la información',
            'A.5.14' => 'Transferencia de información',
            'A.5.15' => 'Control de acceso',
            'A.5.16' => 'Gestión de identidad',
            'A.5.17' => 'Información de autenticación',
            'A.5.18' => 'Derechos de acceso',
            'A.5.19' => 'Seguridad de la información en relaciones con proveedores',
            'A.5.20' => 'Seguridad de la información en acuerdos con proveedores',
            'A.5.21' => 'Gestión de seguridad en la cadena de suministro TIC',
            'A.5.22' => 'Monitoreo, revisión y gestión de cambios de servicios de proveedores',
            'A.5.23' => 'Seguridad de la información para uso de servicios en la nube',
            'A.5.24' => 'Planificación y preparación de gestión de incidentes de seguridad',
            'A.5.25' => 'Evaluación y decisión sobre eventos de seguridad de la información',
            'A.5.26' => 'Respuesta a incidentes de seguridad de la información',
            'A.5.27' => 'Aprendizaje de incidentes de seguridad de la información',
            'A.5.28' => 'Recolección de evidencias',
            'A.5.29' => 'Seguridad de la información durante la disrupción',
            'A.5.30' => 'Preparación TIC para continuidad del negocio',
            'A.5.31' => 'Requisitos legales, estatutarios, reglamentarios y contractuales',
            'A.5.32' => 'Derechos de propiedad intelectual',
            'A.5.33' => 'Protección de registros',
            'A.5.34' => 'Privacidad y protección de datos personales',
            'A.5.35' => 'Revisión independiente de la seguridad de la información',
            'A.5.36' => 'Cumplimiento de políticas, normas y estándares de seguridad',
            'A.5.37' => 'Procedimientos operativos documentados',
        ];
    }

    /** 8 controles de personas (A.6) */
    private function controlesPersonas(): array
    {
        return [
            'A.6.1' => 'Investigación de antecedentes',
            'A.6.2' => 'Términos y condiciones del empleo',
            'A.6.3' => 'Concienciación, educación y capacitación en seguridad',
            'A.6.4' => 'Proceso disciplinario',
            'A.6.5' => 'Responsabilidades tras la terminación o cambio de empleo',
            'A.6.6' => 'Acuerdos de confidencialidad o no divulgación',
            'A.6.7' => 'Trabajo remoto',
            'A.6.8' => 'Reporte de eventos de seguridad de la información',
        ];
    }

    /** 14 controles físicos (A.7) */
    private function controlesFisicos(): array
    {
        return [
            'A.7.1' => 'Perímetros de seguridad física',
            'A.7.2' => 'Entrada física',
            'A.7.3' => 'Seguridad de oficinas, despachos e instalaciones',
            'A.7.4' => 'Monitoreo de seguridad física',
            'A.7.5' => 'Protección contra amenazas físicas y ambientales',
            'A.7.6' => 'Trabajo en áreas seguras',
            'A.7.7' => 'Escritorio y pantalla limpios',
            'A.7.8' => 'Ubicación y protección de equipos',
            'A.7.9' => 'Seguridad de activos fuera de las instalaciones',
            'A.7.10' => 'Medios de almacenamiento',
            'A.7.11' => 'Servicios de suministro',
            'A.7.12' => 'Seguridad del cableado',
            'A.7.13' => 'Mantenimiento de equipos',
            'A.7.14' => 'Eliminación o reutilización segura de equipos',
        ];
    }

    /** 34 controles tecnológicos (A.8) */
    private function controlesTecnologicos(): array
    {
        return [
            'A.8.1' => 'Dispositivos endpoint de usuario',
            'A.8.2' => 'Derechos de acceso privilegiado',
            'A.8.3' => 'Restricción de acceso a la información',
            'A.8.4' => 'Acceso al código fuente',
            'A.8.5' => 'Autenticación segura',
            'A.8.6' => 'Gestión de capacidad',
            'A.8.7' => 'Protección contra malware',
            'A.8.8' => 'Gestión de vulnerabilidades técnicas',
            'A.8.9' => 'Gestión de configuración',
            'A.8.10' => 'Eliminación de información',
            'A.8.11' => 'Enmascaramiento de datos',
            'A.8.12' => 'Prevención de fuga de datos',
            'A.8.13' => 'Respaldo de información',
            'A.8.14' => 'Redundancia de instalaciones de procesamiento de información',
            'A.8.15' => 'Registro de eventos (logging)',
            'A.8.16' => 'Actividades de monitoreo',
            'A.8.17' => 'Sincronización de relojes',
            'A.8.18' => 'Uso de programas de utilidad privilegiados',
            'A.8.19' => 'Instalación de software en sistemas operativos',
            'A.8.20' => 'Seguridad de redes',
            'A.8.21' => 'Seguridad de los servicios de red',
            'A.8.22' => 'Segregación de redes',
            'A.8.23' => 'Filtrado web',
            'A.8.24' => 'Uso de criptografía',
            'A.8.25' => 'Ciclo de vida de desarrollo seguro',
            'A.8.26' => 'Requisitos de seguridad de aplicaciones',
            'A.8.27' => 'Principios de arquitectura e ingeniería de sistemas seguros',
            'A.8.28' => 'Codificación segura',
            'A.8.29' => 'Pruebas de seguridad en desarrollo y aceptación',
            'A.8.30' => 'Desarrollo subcontratado',
            'A.8.31' => 'Separación de entornos de desarrollo, prueba y producción',
            'A.8.32' => 'Gestión de cambios',
            'A.8.33' => 'Información de prueba',
            'A.8.34' => 'Protección de sistemas de información durante pruebas de auditoría',
        ];
    }
}