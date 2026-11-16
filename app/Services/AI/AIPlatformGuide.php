<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Conocimiento fijo sobre el funcionamiento de GSI UPTex. Lo redacta el
 * equipo del proyecto (no es material normativo con derechos de autor) y se
 * entrega al modelo como referencia citable «Guía de la plataforma GSI UPTex».
 */
final class AIPlatformGuide
{
    public const FUENTE = 'Guía de la plataforma GSI UPTex';

    public static function texto(): string
    {
        return <<<'GUIA'
GSI UPTex es el Sistema de Gestión de Seguridad de la Información (SGSI) de la Universidad Politécnica de Texcoco, alineado a ISO/IEC 27001:2022. Módulos (cada usuario ve solo los que su rol permite):
- Dashboard: indicadores generales (cumplimiento ISO, riesgos críticos, incidentes abiertos, auditorías en curso, documentos por vencer, inventario).
- Activos: registro de activos de información (tipos: hardware, software, información, servicio, infraestructura) con confidencialidad, integridad y disponibilidad en escala 1-5; la criticidad es el máximo de las tres. Cada activo tiene departamento, ubicación, responsable, ficha técnica y código QR. Botón «Nuevo activo» en Activos.
- Inventario TI: edificios, laboratorios, computadoras, software instalado, antivirus y mantenimientos. Cada computadora es también un activo.
- Riesgos: un riesgo une activo + amenaza + vulnerabilidad. Probabilidad e impacto en escala 1-5; el nivel (bajo, medio, alto, crítico) se resuelve con la matriz de riesgo configurada 5x5. El impacto sugerido proviene de la criticidad del activo y la probabilidad sugerida de la categoría de la amenaza ajustada por los controles del activo. Tratamientos: mitigar, transferir, evitar o aceptar. Se registra riesgo residual (probabilidad e impacto tras el tratamiento) y fecha de revisión. Botón «Registrar riesgo» en Riesgos.
- Incidentes: se reportan con título, descripción, activo afectado y fecha de detección. Estados: abierto → en_analisis → contenido → resuelto → cerrado (también abierto/en_analisis → cerrado). Admiten evidencias, comentarios internos y línea de tiempo.
- Auditorías: alcance, fechas y auditor. Se registran hallazgos (menor, mayor, crítica); un hallazgo puede generar una no conformidad con acción correctiva (responsable y fecha compromiso). Las no conformidades se cierran cuando sus acciones correctivas están cerradas. Existe la vista «Acciones correctivas».
- Documentos: políticas y procedimientos con versiones. Estados: borrador → en_revision → aprobado → publicado → obsoleto. Cada versión puede tener vigencia.
- Cumplimiento ISO: estado de implementación de los 93 controles del Anexo A (no_implementado, en_proceso, implementado, no_aplica).
- Reportes: exportación de información del SGSI. Notificaciones, búsqueda global y «Mi perfil» para todos.
- Administración (solo Administrador): usuarios, roles y permisos, configuración y bitácora.
- SGSI Intelligence Assistant: este asistente. Disponible en el menú principal y dentro de Activos, Riesgos, Incidentes, Auditorías, Documentos y Dashboard. Sus análisis son propuestas: nunca modifica registros por sí mismo; el usuario las revisa y decide.
Roles: Administrador, Responsable SGSI, Auditor, Responsable de Activo, Director, Consulta.
GUIA;
    }
}
