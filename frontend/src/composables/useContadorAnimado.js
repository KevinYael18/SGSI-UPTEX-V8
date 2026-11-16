import { ref, watch, onBeforeUnmount } from 'vue';

/**
 * Contador progresivo para indicadores numéricos (solo presentación).
 *
 * - Interpreta valores como 12, "85.5%", "1,204" o "3 días": anima la parte
 *   numérica inicial y conserva el resto tal cual.
 * - Al terminar muestra EXACTAMENTE el valor original recibido (mismo texto),
 *   así que nunca se altera el dato del backend; los cuadros intermedios solo
 *   duran ~0.9 s.
 * - Si el valor no es numérico, o el usuario prefiere movimiento reducido, se
 *   muestra directamente sin animar.
 * - Una sola animación por cambio con requestAnimationFrame (sin intervalos);
 *   se cancela al desmontar.
 */
const PATRON = /^(\s*)(-?\d{1,3}(?:,\d{3})+|-?\d+)(\.\d+)?(.*)$/s;

function movimientoReducido() {
    return typeof window !== 'undefined'
        && typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function analizar(valor) {
    if (valor === null || valor === undefined) return null;
    const texto = String(valor);
    const m = texto.match(PATRON);
    if (!m) return null;
    const conMiles = m[2].includes(',');
    const decimales = m[3] ? m[3].length - 1 : 0;
    const numero = Number(m[2].replace(/,/g, '') + (m[3] || ''));
    if (!Number.isFinite(numero)) return null;
    return { texto, prefijo: m[1], numero, decimales, conMiles, sufijo: m[4] };
}

function formatear(n, p) {
    let cuerpo = n.toFixed(p.decimales);
    if (p.conMiles) {
        const [entero, frac] = cuerpo.split('.');
        cuerpo = entero.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + (frac ? `.${frac}` : '');
    }
    return `${p.prefijo}${cuerpo}${p.sufijo}`;
}

const easeOutCubic = (t) => 1 - (1 - t) ** 3;

export function useContadorAnimado(fuente, { duracion = 900 } = {}) {
    const mostrado = ref(fuente() ?? '');
    const animando = ref(false);
    let cuadro = null;
    let desde = 0;

    function detener() {
        if (cuadro !== null && typeof cancelAnimationFrame === 'function') cancelAnimationFrame(cuadro);
        cuadro = null;
        animando.value = false;
    }

    function animarHacia(valor) {
        detener();
        const p = analizar(valor);
        if (!p || movimientoReducido() || typeof requestAnimationFrame !== 'function' || p.numero === desde) {
            mostrado.value = valor ?? '';
            desde = p ? p.numero : 0;
            return;
        }
        const inicio = desde;
        let t0 = null;
        animando.value = true;
        const paso = (ahora) => {
            if (t0 === null) t0 = ahora;
            const t = Math.min(1, (ahora - t0) / duracion);
            if (t < 1) {
                mostrado.value = formatear(inicio + (p.numero - inicio) * easeOutCubic(t), p);
                cuadro = requestAnimationFrame(paso);
            } else {
                mostrado.value = p.texto; // valor final idéntico al original
                desde = p.numero;
                cuadro = null;
                animando.value = false;
            }
        };
        mostrado.value = formatear(inicio, p);
        cuadro = requestAnimationFrame(paso);
    }

    watch(fuente, (v) => animarHacia(v), { immediate: true });
    onBeforeUnmount(detener);

    return { mostrado, animando };
}
