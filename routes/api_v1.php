<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ActivoController;
use App\Http\Controllers\Api\V1\ActivoQrController;
use App\Http\Controllers\Api\V1\AuditoriaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BusquedaController;
use App\Http\Controllers\Api\V1\CatalogoController;
use App\Http\Controllers\Api\V1\ComentarioController;
use App\Http\Controllers\Api\V1\ConfiguracionController;
use App\Http\Controllers\Api\V1\ControlIsoController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DocumentoController;
use App\Http\Controllers\Api\V1\EvidenciaController;
use App\Http\Controllers\Api\V1\PerfilController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\IncidenteController;
use App\Http\Controllers\Api\V1\NotificacionController;
use App\Http\Controllers\Api\V1\ReporteController;
use App\Http\Controllers\Api\V1\RiesgoController;
use App\Http\Controllers\Api\V1\TimelineController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\RolController;
use App\Http\Controllers\Api\V1\UsuarioController;
use App\Http\Controllers\Api\V1\BitacoraController;
use App\Http\Controllers\Api\V1\IndicadorController;
use App\Http\Controllers\Api\V1\AccionCorrectivaController;
use App\Http\Controllers\Api\V1\ComputadoraController;
use App\Http\Controllers\Api\V1\InventarioController;
use App\Http\Controllers\Api\V1\SoftwareController;
use App\Http\Controllers\Api\V1\IaController;
use App\Http\Controllers\Api\V1\IaConocimientoController;
use App\Http\Controllers\Api\V1\IaConversacionController;

Route::prefix('v1')->group(function () {
        // Recuperación de contraseña (limitadores con nombre propio, ver AuthServiceProvider).
        Route::post('/olvide-password', [AuthController::class, 'solicitarRecuperacion'])
           ->middleware('throttle:recuperacion-solicitar');
        Route::post('/restablecer-password', [AuthController::class, 'restablecer'])
           ->middleware('throttle:recuperacion-restablecer');
        Route::post('/restablecer-password/validar', [AuthController::class, 'validarTokenRecuperacion'])
           ->middleware('throttle:recuperacion-validar');
        Route::post('/login', [AuthController::class, 'login'])
           ->middleware(['throttle:10,1', \Illuminate\Session\Middleware\StartSession::class]);

        // AsegurarCuentaActiva: una cuenta suspendida pierde el acceso en su
        // siguiente petición, aunque tenga una sesión o token emitidos antes.
        Route::middleware(['auth:sanctum', \App\Http\Middleware\AsegurarCuentaActiva::class])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Módulo «Mi perfil»: siempre sobre el usuario autenticado (sin IDs en la ruta).
        Route::get('/perfil', [PerfilController::class, 'show']);
        Route::put('/perfil', [PerfilController::class, 'update'])->middleware('throttle:perfil-datos');
        Route::post('/perfil/foto', [PerfilController::class, 'subirFoto'])->middleware('throttle:perfil-foto');
        Route::delete('/perfil/foto', [PerfilController::class, 'eliminarFoto'])->middleware('throttle:perfil-foto');
        Route::put('/perfil/password', [PerfilController::class, 'cambiarPassword'])->middleware('throttle:perfil-password');

        // Rutas fijas de activos ANTES de apiResource (si no, /activos/{activo} las capturaría).
        Route::get('/activos/tipos-tecnicos', [\App\Http\Controllers\Api\V1\TiposActivoController::class, 'index']);
        Route::get('/activos/reconocer', [\App\Http\Controllers\Api\V1\TiposActivoController::class, 'reconocer'])->middleware('throttle:120,1');
                Route::apiResource('activos', ActivoController::class);
        Route::get('/activos/{activo}/qr', ActivoQrController::class);
        Route::get('/riesgos/impacto-sugerido', [RiesgoController::class, 'impactoSugerido']);
        Route::get('/riesgos/sugerencia', [RiesgoController::class, 'sugerencia']);
        Route::apiResource('riesgos', RiesgoController::class)->only(['index', 'store', 'update', 'destroy']);
        // SGSI Intelligence Assistant — análisis de riesgos (evolución del «Asistente de IA» original).
        // Limitador propio (ver AuthServiceProvider): cada llamada consume cuota externa.
        Route::post('/ia/sugerir-riesgo', [IaController::class, 'sugerirRiesgo'])->middleware('throttle:ia-sugerencias');

        // SGSI Intelligence Assistant (proveedor interno: Gemini, vía App\Services\AI\GeminiService).
        // Rutas fijas antes que las parametrizadas.
        Route::get('/ia/estado', [IaController::class, 'estado']);
        Route::get('/ia/consumo', [IaController::class, 'consumo']);
        Route::post('/ia/chat', [IaConversacionController::class, 'chat'])->middleware('throttle:ia-chat');
        Route::get('/ia/conversaciones', [IaConversacionController::class, 'index']);
        Route::get('/ia/conversaciones/{uuid}', [IaConversacionController::class, 'show'])->whereUuid('uuid');
        Route::delete('/ia/conversaciones/{uuid}', [IaConversacionController::class, 'destroy'])->whereUuid('uuid');
        Route::post('/ia/analizar/activo', [IaController::class, 'analizarActivo'])->middleware('throttle:ia-sugerencias');
        Route::post('/ia/analizar/incidentes/{incidente}', [IaController::class, 'analizarIncidente'])->middleware('throttle:ia-sugerencias');
        Route::post('/ia/analizar/auditorias/{auditoria}', [IaController::class, 'analizarAuditoria'])->middleware('throttle:ia-sugerencias');
        Route::post('/ia/analizar/documentos/{documento}', [IaController::class, 'analizarDocumento'])->middleware('throttle:ia-sugerencias');
        Route::post('/ia/analizar/dashboard', [IaController::class, 'analizarDashboard'])->middleware('throttle:ia-sugerencias');
        Route::patch('/ia/analisis/{uuid}/decision', [IaController::class, 'decidir'])->whereUuid('uuid');
        Route::get('/ia/conocimiento', [IaConocimientoController::class, 'index']);
        Route::post('/ia/conocimiento', [IaConocimientoController::class, 'store'])->middleware('throttle:ia-conocimiento');
        Route::patch('/ia/conocimiento/{uuid}', [IaConocimientoController::class, 'update'])->whereUuid('uuid');
        Route::delete('/ia/conocimiento/{uuid}', [IaConocimientoController::class, 'destroy'])->whereUuid('uuid');

        Route::get('/incidentes', [IncidenteController::class, 'index']);
        Route::post('/incidentes', [IncidenteController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/incidentes/{incidente}', [IncidenteController::class, 'show']);
        Route::put('/incidentes/{incidente}', [IncidenteController::class, 'update']);
        Route::delete('/incidentes/{incidente}', [IncidenteController::class, 'destroy']);
        Route::patch('/incidentes/{incidente}/estado', [IncidenteController::class, 'cambiarEstado']);

        Route::get('/evidencias/{evidencia}/descargar', [EvidenciaController::class, 'descargar']);
        Route::get('/evidencias/{evidencia}/verificar', [EvidenciaController::class, 'verificar'])->middleware('throttle:30,1');

        Route::get('/auditorias', [AuditoriaController::class, 'index']);
        Route::post('/auditorias', [AuditoriaController::class, 'store']);
        Route::get('/auditorias/{auditoria}', [AuditoriaController::class, 'show']);
        Route::put('/auditorias/{auditoria}', [AuditoriaController::class, 'update']);
        Route::delete('/auditorias/{auditoria}', [AuditoriaController::class, 'destroy']);
        Route::post('/auditorias/{auditoria}/hallazgos', [AuditoriaController::class, 'registrarHallazgo']);
        Route::patch('/no-conformidades/{no_conformidad}/cerrar', [AuditoriaController::class, 'cerrarNoConformidad']);
        Route::get('/acciones-correctivas', [AccionCorrectivaController::class, 'index']);
        Route::patch('/acciones-correctivas/{accion_correctiva}/cerrar', [AccionCorrectivaController::class, 'cerrar']);

        Route::get('/documentos', [DocumentoController::class, 'index']);
        Route::post('/documentos', [DocumentoController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/documentos/{documento}', [DocumentoController::class, 'show']);
        Route::post('/documentos/{documento}/versiones', [DocumentoController::class, 'nuevaVersion'])->middleware('throttle:20,1');
        Route::patch('/documentos/{documento}/estado', [DocumentoController::class, 'cambiarEstado']);
        Route::put('/documentos/{documento}', [DocumentoController::class, 'update']);
        Route::delete('/documentos/{documento}', [DocumentoController::class, 'destroy']);

        Route::get('/controles-iso', [ControlIsoController::class, 'index']);
        Route::patch('/controles-iso/{control_iso}/estado', [ControlIsoController::class, 'actualizarEstado']);
        Route::get('/cumplimiento/resumen', [ControlIsoController::class, 'resumen']);

        Route::get('/notificaciones', [NotificacionController::class, 'index']);
        Route::get('/notificaciones/no-leidas/conteo', [NotificacionController::class, 'contarNoLeidas']);
        Route::patch('/notificaciones/{notificacion}/leida', [NotificacionController::class, 'marcarLeida']);
        Route::post('/notificaciones/marcar-todas-leidas', [NotificacionController::class, 'marcarTodasLeidas']);

        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/matriz-riesgo', [DashboardController::class, 'matrizRiesgo']);
        Route::get('/dashboard/graficas', [DashboardController::class, 'graficas']);

        Route::get('/{tipo}/{identificador}/timeline', [TimelineController::class, 'index'])
            ->whereIn('tipo', \App\Support\RegistroTimeline::tiposValidos());

        Route::get('/busqueda', [BusquedaController::class, 'index']);

        Route::get('/catalogos/departamentos', [CatalogoController::class, 'departamentos']);
        Route::get('/catalogos/ubicaciones', [CatalogoController::class, 'ubicaciones']);
        Route::get('/catalogos/usuarios', [CatalogoController::class, 'usuarios']);
        Route::get('/catalogos/activos', [CatalogoController::class, 'activos']);
        Route::get('/catalogos/amenazas', [CatalogoController::class, 'amenazas']);
        Route::get('/catalogos/vulnerabilidades', [CatalogoController::class, 'vulnerabilidades']);

        Route::get('/reportes/activos.pdf', [ReporteController::class, 'activos']);
        Route::get('/reportes/riesgos.pdf', [ReporteController::class, 'riesgos']);
        Route::get('/reportes/activos.xlsx', [ReporteController::class, 'activosExcel']);
        Route::get('/reportes/riesgos.xlsx', [ReporteController::class, 'riesgosExcel']);
        Route::get('/reportes/indicadores.pdf', [ReporteController::class, 'indicadoresPdf']);
        Route::get('/reportes/indicadores.xlsx', [ReporteController::class, 'indicadoresExcel']);

        Route::get('/indicadores', [IndicadorController::class, 'index']);

        // ---- Inventario tecnológico (edificios, laboratorios, computadoras, software) ----
        Route::get('/inventario/catalogos', [InventarioController::class, 'catalogos']);
        Route::get('/inventario/resumen', [InventarioController::class, 'resumen']);
        Route::post('/inventario/edificios', [InventarioController::class, 'guardarEdificio']);
        Route::put('/inventario/edificios/{edificio}', [InventarioController::class, 'actualizarEdificio']);
        Route::delete('/inventario/edificios/{edificio}', [InventarioController::class, 'eliminarEdificio']);
        Route::post('/inventario/laboratorios', [InventarioController::class, 'guardarLaboratorio']);
        Route::get('/inventario/laboratorios/{laboratorio}', [InventarioController::class, 'laboratorio']);
        Route::put('/inventario/laboratorios/{laboratorio}', [InventarioController::class, 'actualizarLaboratorio']);
        Route::delete('/inventario/laboratorios/{laboratorio}', [InventarioController::class, 'eliminarLaboratorio']);

        // Rutas fijas ANTES de /computadoras/{computadora} para que no colisionen.
        Route::get('/computadoras/exportar.xlsx', [ComputadoraController::class, 'exportar']);
        Route::get('/computadoras/plantilla.xlsx', [ComputadoraController::class, 'plantilla']);
        Route::post('/computadoras/importar/analizar', [ComputadoraController::class, 'analizarImportacion'])->middleware('throttle:20,1');
        Route::post('/computadoras/importar', [ComputadoraController::class, 'importar'])->middleware('throttle:10,1');
        Route::get('/computadoras', [ComputadoraController::class, 'index']);
        Route::post('/computadoras', [ComputadoraController::class, 'store']);
        Route::get('/computadoras/{computadora}', [ComputadoraController::class, 'show']);
        Route::put('/computadoras/{computadora}', [ComputadoraController::class, 'update']);
        Route::delete('/computadoras/{computadora}', [ComputadoraController::class, 'destroy']);
        Route::post('/computadoras/{computadora}/software', [ComputadoraController::class, 'instalarSoftware']);
        Route::put('/computadoras/{computadora}/software/{software}', [ComputadoraController::class, 'actualizarSoftware']);
        Route::delete('/computadoras/{computadora}/software/{software}', [ComputadoraController::class, 'quitarSoftware']);
        Route::post('/computadoras/{computadora}/mantenimientos', [ComputadoraController::class, 'registrarMantenimiento']);

        Route::get('/inventario/ubicaciones', [\App\Http\Controllers\Api\V1\UbicacionController::class, 'index']);
        Route::post('/inventario/ubicaciones', [\App\Http\Controllers\Api\V1\UbicacionController::class, 'store']);
        Route::put('/inventario/ubicaciones/{ubicacion}', [\App\Http\Controllers\Api\V1\UbicacionController::class, 'update']);
        Route::delete('/inventario/ubicaciones/{ubicacion}', [\App\Http\Controllers\Api\V1\UbicacionController::class, 'destroy']);
        Route::get('/software/{software}/equipos', [SoftwareController::class, 'equipos']);
        Route::get('/software', [SoftwareController::class, 'index']);
        Route::post('/software', [SoftwareController::class, 'store']);
        Route::put('/software/{software}', [SoftwareController::class, 'update']);
        Route::delete('/software/{software}', [SoftwareController::class, 'destroy']);

        Route::get('/configuracion', [ConfiguracionController::class, 'index']);
        Route::put('/configuracion', [ConfiguracionController::class, 'update']);
        
        Route::get('/usuarios', [UsuarioController::class, 'index']);
        Route::post('/usuarios', [UsuarioController::class, 'store']);
        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update']);
        Route::patch('/usuarios/{usuario}/suspender', [UsuarioController::class, 'suspender']);

        Route::get('/roles', [RolController::class, 'index']);
        Route::get('/permisos', [RolController::class, 'permisosDisponibles']);
        Route::put('/roles/{rol}', [RolController::class, 'actualizarPermisos']);
        Route::get('/bitacora', [BitacoraController::class, 'index']);
        Route::get('/bitacora/entidades', [BitacoraController::class, 'entidadesDisponibles']);
        Route::get('/bitacora.pdf', [BitacoraController::class, 'exportarPdf']);
        Route::get('/bitacora.xlsx', [BitacoraController::class, 'exportarExcel']);

        Route::get('/{tipo}/{identificador}/comentarios', [ComentarioController::class, 'index'])
            ->whereIn('tipo', \App\Support\RegistroComentables::tiposValidos());
        Route::post('/{tipo}/{identificador}/comentarios', [ComentarioController::class, 'store'])
            ->whereIn('tipo', \App\Support\RegistroComentables::tiposValidos());
        Route::delete('/comentarios/{comentario}', [ComentarioController::class, 'destroy']);
    });
});

// Este archivo se publica como routes/api.php (prefijo /api): la ruta queda en
// /api/health, la que usa el healthcheck de Docker. Antes estaba declarada como
// '/api/health' y quedaba en /api/api/health; el healthcheck "pasaba" solo
// porque el fallback del SPA respondía 200 con el HTML.
Route::get('/health', HealthController::class);