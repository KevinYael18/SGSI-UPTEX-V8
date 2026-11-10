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

Route::prefix('v1')->group(function () {
        Route::post('/olvide-password', [AuthController::class, 'solicitarRecuperacion'])
           ->middleware('throttle:5,1');
        Route::post('/restablecer-password', [AuthController::class, 'restablecer'])
           ->middleware('throttle:5,1');
        Route::post('/login', [AuthController::class, 'login'])
           ->middleware(['throttle:10,1', \Illuminate\Session\Middleware\StartSession::class]);

        Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Módulo «Mi perfil»: siempre sobre el usuario autenticado (sin IDs en la ruta).
        Route::get('/perfil', [PerfilController::class, 'show']);
        Route::put('/perfil', [PerfilController::class, 'update'])->middleware('throttle:perfil-datos');
        Route::post('/perfil/foto', [PerfilController::class, 'subirFoto'])->middleware('throttle:perfil-foto');
        Route::delete('/perfil/foto', [PerfilController::class, 'eliminarFoto'])->middleware('throttle:perfil-foto');
        Route::put('/perfil/password', [PerfilController::class, 'cambiarPassword'])->middleware('throttle:perfil-password');

        Route::apiResource('activos', ActivoController::class);
        Route::get('/activos/{activo}/qr', ActivoQrController::class);
        Route::get('/riesgos/impacto-sugerido', [RiesgoController::class, 'impactoSugerido']);
        Route::get('/riesgos/sugerencia', [RiesgoController::class, 'sugerencia']);
        Route::apiResource('riesgos', RiesgoController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('/incidentes', [IncidenteController::class, 'index']);
        Route::post('/incidentes', [IncidenteController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/incidentes/{incidente}', [IncidenteController::class, 'show']);
        Route::put('/incidentes/{incidente}', [IncidenteController::class, 'update']);
        Route::delete('/incidentes/{incidente}', [IncidenteController::class, 'destroy']);
        Route::patch('/incidentes/{incidente}/estado', [IncidenteController::class, 'cambiarEstado']);

        Route::get('/evidencias/{evidencia}/descargar', [EvidenciaController::class, 'descargar']);

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

Route::get('/api/health', HealthController::class);