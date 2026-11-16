import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { defineComponent, h, ref, nextTick } from 'vue';
import { useContadorAnimado } from '../composables/useContadorAnimado';
import StatCard from '../components/ui/StatCard.vue';

// requestAnimationFrame controlable: cada llamada a avanzar() simula un cuadro.
let cola = [];
let reloj = 0;
function avanzar(ms) {
    reloj += ms;
    const pendientes = cola;
    cola = [];
    pendientes.forEach((cb) => cb(reloj));
}

beforeEach(() => {
    cola = [];
    reloj = 0;
    vi.stubGlobal('requestAnimationFrame', (cb) => { cola.push(cb); return cola.length; });
    vi.stubGlobal('cancelAnimationFrame', () => {});
    vi.stubGlobal('matchMedia', () => ({ matches: false }));
});
afterEach(() => vi.unstubAllGlobals());

function montarContador(valorInicial) {
    const valor = ref(valorInicial);
    let expuesto;
    const Comp = defineComponent({
        setup() {
            expuesto = useContadorAnimado(() => valor.value, { duracion: 500 });
            return () => h('span', expuesto.mostrado.value);
        },
    });
    const wrapper = mount(Comp);
    return { wrapper, valor, get mostrado() { return expuesto.mostrado.value; } };
}

describe('useContadorAnimado (no altera los datos)', () => {
    it.each([
        [42, '42'],
        ['85.5%', '85.5%'],
        ['1,204', '1,204'],
        ['3 días', '3 días'],
    ])('termina mostrando exactamente el valor original (%s)', (entrada, esperado) => {
        const c = montarContador(entrada);
        avanzar(0);
        avanzar(250);
        avanzar(600);
        expect(String(c.mostrado)).toBe(esperado);
    });

    it('nunca supera el valor real durante la animación', () => {
        const c = montarContador(100);
        const vistos = [];
        for (let t = 0; t <= 700; t += 50) { avanzar(50); vistos.push(Number(c.mostrado)); }
        expect(Math.max(...vistos)).toBe(100);
        expect(vistos.every((v) => v >= 0 && v <= 100)).toBe(true);
    });

    it('muestra sin animar los valores no numéricos', () => {
        const c = montarContador('N/D');
        expect(c.mostrado).toBe('N/D');
    });

    it('con movimiento reducido muestra el valor final de inmediato', () => {
        vi.stubGlobal('matchMedia', () => ({ matches: true }));
        const c = montarContador(57);
        expect(c.mostrado).toBe(57);
    });

    it('anima de un valor al siguiente cuando el backend envía datos nuevos', async () => {
        const c = montarContador(10);
        avanzar(0); avanzar(600);
        c.valor.value = 20;
        await nextTick();
        avanzar(0); avanzar(600);
        expect(c.mostrado).toBe('20');
    });
});

describe('StatCard', () => {
    it('expone siempre el valor real a lectores de pantalla', () => {
        const wrapper = mount(StatCard, { props: { label: 'Riesgos críticos', valor: 7 } });
        expect(wrapper.find('.visually-hidden').text()).toBe('7');
    });
});

describe('RestablecerPasswordView', () => {
    it('muestra "Enlace no válido" si el enlace no trae token ni correo', async () => {
        vi.resetModules();
        vi.doMock('vue-router', () => ({
            useRoute: () => ({ query: {} }),
            useRouter: () => ({ push: vi.fn() }),
            RouterLink: { template: '<a><slot /></a>' },
        }));
        const { default: Vista } = await import('../views/auth/RestablecerPasswordView.vue');
        const wrapper = mount(Vista, { global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } } });
        await flushPromises();
        expect(wrapper.text()).toContain('Enlace no válido');
        expect(wrapper.text()).toContain('Solicitar un enlace nuevo');
        vi.doUnmock('vue-router');
    });
});
