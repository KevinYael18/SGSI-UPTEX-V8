import http from './http';

/**
 * SGSI Intelligence Assistant. El navegador nunca habla con el proveedor de
 * IA: todo pasa por el backend (/api/v1/ia/*), que valida permisos, dominio,
 * consumo y respuestas. La API key vive solo en el servidor.
 */
export default {
    estado: () => http.get('/v1/ia/estado'),

    // Conversación
    conversaciones: () => http.get('/v1/ia/conversaciones'),
    conversacion: (uuid) => http.get(`/v1/ia/conversaciones/${uuid}`),
    enviar: (mensaje, conversacionUuid) => http.post('/v1/ia/chat', { mensaje, conversacion_uuid: conversacionUuid || undefined }),
    eliminarConversacion: (uuid) => http.delete(`/v1/ia/conversaciones/${uuid}`),

    // Análisis contextuales (propuestas revisables)
    analizarActivo: (datos) => http.post('/v1/ia/analizar/activo', datos),
    analizarIncidente: (uuid) => http.post(`/v1/ia/analizar/incidentes/${uuid}`),
    analizarAuditoria: (id, notas) => http.post(`/v1/ia/analizar/auditorias/${id}`, { notas: notas || undefined }),
    analizarDocumento: (uuid, pregunta) => http.post(`/v1/ia/analizar/documentos/${uuid}`, { pregunta: pregunta || undefined }),
    analizarDashboard: () => http.post('/v1/ia/analizar/dashboard'),
    decidir: (analisisUuid, decision, elementosAplicados = []) =>
        http.patch(`/v1/ia/analisis/${analisisUuid}/decision`, { decision, elementos_aplicados: elementosAplicados }),

    // Base de conocimiento (RAG) y consumo
    conocimiento: () => http.get('/v1/ia/conocimiento'),
    cargarConocimiento: (formData) => http.post('/v1/ia/conocimiento', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
    activarConocimiento: (uuid, activo) => http.patch(`/v1/ia/conocimiento/${uuid}`, { activo }),
    eliminarConocimiento: (uuid) => http.delete(`/v1/ia/conocimiento/${uuid}`),
    consumo: () => http.get('/v1/ia/consumo'),
};
