/**
 * Reconocimiento del tipo específico de activo a partir del nombre.
 * MISMO algoritmo que App\Support\CatalogoTiposActivo::reconocer() (backend);
 * las palabras clave llegan del backend (GET /activos/tipos-tecnicos), así que
 * hay una sola fuente de reglas. Se ejecuta localmente mientras el usuario
 * escribe (sin peticiones por cada tecla).
 */
export function normalizar(texto) {
    return String(texto || '')
        .toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * @param {string} categoria  clasificación general (hardware, software…)
 * @param {string} nombre     nombre escrito por el usuario
 * @param {Array}  tipos      [{ clave, etiqueta, categoria, palabras: { frase: peso } }]
 * @returns {{ subtipo: string|null, confianza: 'alta'|'media'|'ninguna', candidatos: Array }}
 */
export function reconocerTipo(categoria, nombre, tipos) {
    const texto = ` ${normalizar(nombre)} `;
    const puntajes = [];
    for (const tipo of tipos || []) {
        if (categoria && tipo.categoria !== categoria) continue;
        let max = 0;
        let coincidencias = 0;
        for (const [frase, peso] of Object.entries(tipo.palabras || {})) {
            if (texto.includes(` ${normalizar(frase)} `)) {
                max = Math.max(max, Number(peso));
                coincidencias += 1;
            }
        }
        if (max > 0) {
            puntajes.push({ subtipo: tipo.clave, etiqueta: tipo.etiqueta, puntaje: Math.round((max + 0.1 * (coincidencias - 1)) * 100) / 100 });
        }
    }
    puntajes.sort((a, b) => b.puntaje - a.puntaje);
    const candidatos = puntajes.slice(0, 3);
    if (!candidatos.length) return { subtipo: null, confianza: 'ninguna', candidatos: [] };
    const primero = candidatos[0].puntaje;
    const segundo = candidatos[1]?.puntaje ?? 0;
    return { subtipo: candidatos[0].subtipo, confianza: primero >= 3 && primero - segundo >= 1 ? 'alta' : 'media', candidatos };
}

/** Campos aplicables a un subtipo, por secciones (respeta solo_/excepto_subtipos). */
export function seccionesDe(subtipo, catalogo) {
    const tipo = (catalogo?.tipos || []).find((t) => t.clave === subtipo);
    if (!tipo) return [];
    return (catalogo.plantillas?.[tipo.plantilla] || [])
        .map((s) => ({
            titulo: s.titulo,
            campos: s.campos.filter((c) => (!c.solo_subtipos || c.solo_subtipos.includes(subtipo))
                && (!c.excepto_subtipos || !c.excepto_subtipos.includes(subtipo))),
        }))
        .filter((s) => s.campos.length);
}

/** ¿Se muestra el campo según otros valores? (p. ej. IP solo si hay conexión de red) */
export function campoVisible(campo, valores) {
    if (!campo.mostrar_si) return true;
    return Object.entries(campo.mostrar_si).every(([clave, permitidos]) => permitidos.includes(valores?.[clave]));
}
