import http from './http';

/** Inventario tecnológico: edificios, laboratorios, computadoras y catálogo de software. */
const api = {
    catalogos: () => http.get('/v1/inventario/catalogos'),
    resumen: () => http.get('/v1/inventario/resumen'),

    crearEdificio: (datos) => http.post('/v1/inventario/edificios', datos),
    actualizarEdificio: (id, datos) => http.put(`/v1/inventario/edificios/${id}`, datos),
    eliminarEdificio: (id) => http.delete(`/v1/inventario/edificios/${id}`),

    laboratorio: (id) => http.get(`/v1/inventario/laboratorios/${id}`),
    crearLaboratorio: (datos) => http.post('/v1/inventario/laboratorios', datos),
    actualizarLaboratorio: (id, datos) => http.put(`/v1/inventario/laboratorios/${id}`, datos),
    eliminarLaboratorio: (id) => http.delete(`/v1/inventario/laboratorios/${id}`),

    computadoras: (params) => http.get('/v1/computadoras', { params }),
    computadora: (uuid) => http.get(`/v1/computadoras/${uuid}`),
    crearComputadora: (datos) => http.post('/v1/computadoras', datos),
    actualizarComputadora: (uuid, datos) => http.put(`/v1/computadoras/${uuid}`, datos),
    eliminarComputadora: (uuid) => http.delete(`/v1/computadoras/${uuid}`),
    instalarSoftware: (uuid, datos) => http.post(`/v1/computadoras/${uuid}/software`, datos),
    actualizarSoftware: (uuid, softwareId, datos) => http.put(`/v1/computadoras/${uuid}/software/${softwareId}`, datos),
    quitarSoftware: (uuid, softwareId) => http.delete(`/v1/computadoras/${uuid}/software/${softwareId}`),
    registrarMantenimiento: (uuid, datos) => http.post(`/v1/computadoras/${uuid}/mantenimientos`, datos),

    analizarImportacion: (formData) => http.post('/v1/computadoras/importar/analizar', formData),
    importar: (formData) => http.post('/v1/computadoras/importar', formData),

    software: (params) => http.get('/v1/software', { params }),
    crearSoftware: (datos) => http.post('/v1/software', datos),
    actualizarProgramaCatalogo: (id, datos) => http.put(`/v1/software/${id}`, datos),
    eliminarSoftware: (id) => http.delete(`/v1/software/${id}`),
    equiposDeSoftware: (id) => http.get(`/v1/software/${id}/equipos`),

    ubicaciones: () => http.get('/v1/inventario/ubicaciones'),
    crearUbicacion: (datos) => http.post('/v1/inventario/ubicaciones', datos),
    actualizarUbicacion: (id, datos) => http.put(`/v1/inventario/ubicaciones/${id}`, datos),
    eliminarUbicacion: (id) => http.delete(`/v1/inventario/ubicaciones/${id}`),

    /** Descarga autenticada (cookie de sesión) de un Excel. */
    async descargar(ruta, params, nombre) {
        const { data } = await http.get(ruta, { params, responseType: 'blob' });
        const url = URL.createObjectURL(data);
        const a = document.createElement('a');
        a.href = url;
        a.download = nombre;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    },
    exportar: (params, nombre = 'inventario.xlsx') => api.descargar('/v1/computadoras/exportar.xlsx', params, nombre),
    plantilla: () => api.descargar('/v1/computadoras/plantilla.xlsx', {}, 'plantilla-importacion-computadoras.xlsx'),
};

export default api;
