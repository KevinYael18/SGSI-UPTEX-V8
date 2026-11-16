/**
 * Utilidades de presentación para Chart.js.
 *
 * Chart.js dibuja sobre <canvas>, que no entiende variables CSS. Estas
 * funciones leen los tokens del sistema de diseño (tokens.css) en tiempo
 * de ejecución, de modo que las gráficas respetan la identidad
 * institucional y cambian de colores al alternar modo claro/oscuro.
 *
 * No alteran los datos de las gráficas: solo colores, tipografía y ejes.
 */

export function leerVariable(nombre, respaldo = '#6B747B') {
    if (typeof window === 'undefined' || !window.getComputedStyle) return respaldo;
    const valor = getComputedStyle(document.documentElement).getPropertyValue(nombre).trim();
    return valor || respaldo;
}

/** Acepta un color literal ('#0A7A3D') o un token ('--c-primary'). */
export function resolverColor(color) {
    if (typeof color === 'string' && color.startsWith('--')) return leerVariable(color);
    return color;
}

/** Versión translúcida de un color hex (para áreas y hover). */
export function conAlfa(hex, alfa) {
    const limpio = (hex || '').replace('#', '');
    if (limpio.length !== 6) return hex;
    const r = parseInt(limpio.slice(0, 2), 16);
    const g = parseInt(limpio.slice(2, 4), 16);
    const b = parseInt(limpio.slice(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${alfa})`;
}

export function estiloBase() {
    const texto = leerVariable('--chart-text');
    const rejilla = leerVariable('--chart-grid');
    const superficie = leerVariable('--surface-raised', '#FFFFFF');
    const textoFuerte = leerVariable('--text', '#161B1D');
    const borde = leerVariable('--border', '#E2E7E4');
    const fuente = { family: "'Inter', 'Segoe UI', system-ui, sans-serif", size: 12 };

    return {
        fuente,
        texto,
        rejilla,
        tooltip: {
            backgroundColor: superficie,
            titleColor: textoFuerte,
            bodyColor: textoFuerte,
            borderColor: borde,
            borderWidth: 1,
            padding: 10,
            cornerRadius: 10,
            displayColors: true,
            boxPadding: 4,
            titleFont: { ...fuente, weight: '600' },
            bodyFont: fuente,
        },
    };
}

/**
 * Observa el atributo data-theme del <html> y ejecuta `alCambiar` cuando
 * el usuario alterna el tema. Devuelve una función para dejar de observar.
 */
export function observarTema(alCambiar) {
    if (typeof MutationObserver === 'undefined') return () => {};
    const observador = new MutationObserver(() => alCambiar());
    observador.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    return () => observador.disconnect();
}

/* ---------------------------------------------------------------------
 * Movimiento de las gráficas (solo presentación, animaciones NATIVAS de
 * Chart.js). Nunca modifican los datos: Chart.js interpola la geometría
 * desde 0 (o desde el valor anterior) hasta el valor real del backend.
 * ------------------------------------------------------------------- */

export function prefiereMovimientoReducido() {
    return typeof window !== 'undefined'
        && typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Animación de entrada para barras: crecen desde cero con desaceleración
 * suave y un escalonado breve entre barras (solo la primera vez; las
 * actualizaciones posteriores interpolan del valor anterior al nuevo sin
 * retrasos). Devuelve `false` si el usuario pidió movimiento reducido.
 * Cada gráfica debe pedir su propia instancia (guarda el estado "ya entró").
 */
export function animacionEntradaBarras({ duracion = 900, escalon = 70 } = {}) {
    if (prefiereMovimientoReducido()) return false;
    let yaEntro = false;
    return {
        duration: duracion,
        easing: 'easeOutQuart',
        onComplete: () => { yaEntro = true; },
        delay: (ctx) => (ctx.type === 'data' && ctx.mode === 'default' && !yaEntro
            ? ctx.dataIndex * escalon + ctx.datasetIndex * 40
            : 0),
    };
}

/** Transición corta para actualizaciones (cambio de tema, datos nuevos). */
export const TRANSICIONES_ACTUALIZACION = {
    active: { animation: { duration: 160 } },
    resize: { animation: { duration: 0 } },
};

/**
 * Ejecuta `alMostrar` la primera vez que el elemento entra en pantalla, para
 * que la animación de entrada se vea aunque la gráfica esté más abajo.
 * Sin IntersectionObserver (o en pruebas) se ejecuta de inmediato.
 * Devuelve una función para cancelar la observación.
 */
export function alSerVisible(elemento, alMostrar) {
    if (!elemento || typeof IntersectionObserver === 'undefined') {
        alMostrar();
        return () => {};
    }
    const observador = new IntersectionObserver((entradas) => {
        if (entradas.some((e) => e.isIntersecting)) {
            observador.disconnect();
            alMostrar();
        }
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    observador.observe(elemento);
    return () => observador.disconnect();
}
