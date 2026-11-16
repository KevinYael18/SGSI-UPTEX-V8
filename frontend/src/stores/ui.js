import { defineStore } from 'pinia';
import { nextTick } from 'vue';

const CLAVE_TEMA = 'sgsi_tema';
const DURACION_TEMA_MS = 460;
let temporizadorTema = null;

function movimientoReducido() {
    return typeof window !== 'undefined'
        && typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Punto de origen del revelado circular: el centro del botón que se presionó
 * (funciona también con teclado, donde clientX/clientY valen 0).
 */
function origenDesde(evento) {
    const objetivo = evento?.currentTarget;
    if (objetivo && typeof objetivo.getBoundingClientRect === 'function') {
        const r = objetivo.getBoundingClientRect();
        if (r.width || r.height) return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
    }
    if (evento && (evento.clientX || evento.clientY)) return { x: evento.clientX, y: evento.clientY };
    return { x: window.innerWidth - 48, y: 32 };
}

export const useUiStore = defineStore('ui', {
    state: () => ({
        tema: localStorage.getItem(CLAVE_TEMA) || 'light',
        sidebarColapsado: false,
        // Solo presentación: menú lateral tipo "drawer" en tablets y celulares.
        menuMovilAbierto: false,
    }),
    actions: {
        aplicarTema() {
            document.documentElement.setAttribute('data-theme', this.tema);
        },
        /**
         * Alterna claro/oscuro. Mismo almacenamiento de siempre (localStorage
         * 'sgsi_tema'); solo se añade la animación del cambio:
         *  - View Transitions disponibles: revelado circular desde el botón.
         *  - Si no: transición cruzada de colores (.tema-transicion, CSS).
         *  - prefers-reduced-motion: cambio inmediato, sin animación.
         */
        alternarTema(evento) {
            const cambiar = () => {
                this.tema = this.tema === 'light' ? 'dark' : 'light';
                localStorage.setItem(CLAVE_TEMA, this.tema);
                this.aplicarTema();
            };

            if (movimientoReducido() || typeof document === 'undefined') {
                cambiar();
                return;
            }

            const raiz = document.documentElement;

            if (typeof document.startViewTransition === 'function' && !document.hidden) {
                const { x, y } = origenDesde(evento);
                const radio = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y));
                const transicion = document.startViewTransition(async () => {
                    cambiar();
                    await nextTick(); // el icono sol/luna ya refleja el nuevo tema
                });
                transicion.ready.then(() => {
                    raiz.animate(
                        { clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${radio}px at ${x}px ${y}px)`] },
                        { duration: DURACION_TEMA_MS, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)', pseudoElement: '::view-transition-new(root)' },
                    );
                }).catch(() => {});
                return;
            }

            raiz.classList.add('tema-transicion');
            cambiar();
            clearTimeout(temporizadorTema);
            temporizadorTema = setTimeout(() => raiz.classList.remove('tema-transicion'), DURACION_TEMA_MS);
        },
        alternarSidebar() {
            this.sidebarColapsado = !this.sidebarColapsado;
        },
        abrirMenuMovil() {
            this.menuMovilAbierto = true;
        },
        cerrarMenuMovil() {
            this.menuMovilAbierto = false;
        },
    },
});
