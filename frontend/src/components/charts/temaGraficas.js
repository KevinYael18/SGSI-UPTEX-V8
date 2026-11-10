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
