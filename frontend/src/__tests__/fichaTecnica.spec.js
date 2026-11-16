import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { reactive, nextTick } from 'vue';
import catalogo from './fixtures/tipos-activo.json';
import { reconocerTipo } from '../composables/useReconocimientoActivo';
import FichaTecnicaActivo, { tecnicoParaEnviar, modeloVacio } from '../components/activos/FichaTecnicaActivo.vue';

// El fixture es la salida real de CatalogoTiposActivo::paraFrontend() (backend):
// frontend y backend usan exactamente las mismas reglas.

describe('reconocimiento automático del tipo de activo', () => {
    it.each([
        ['hardware', 'Computadora Dell OptiPlex', 'computadora'],
        ['hardware', 'PC Laboratorio 1', 'computadora'],
        ['hardware', 'Laptop HP ProBook', 'laptop'],
        ['hardware', 'Servidor Dell PowerEdge', 'servidor'],
        ['hardware', 'Cámara Hikvision', 'camara'],
        ['hardware', 'Impresora HP LaserJet', 'impresora'],
        ['hardware', 'Impresora 3D Creality', 'impresora_3d'],
        ['hardware', 'Switch Cisco', 'switch'],
        ['hardware', 'Router TP-Link', 'router'],
        ['hardware', 'Aire acondicionado', 'climatizacion'],
        ['hardware', 'PLC Siemens', 'plc'],
        ['hardware', 'Máquina CNC', 'cnc'],
        ['software', 'Software Microsoft Office', 'productividad'],
        ['software', 'Windows 11', 'sistema_operativo'],
        ['software', 'ESET Antivirus', 'antivirus'],
    ])('%s · «%s» → %s', (cat, nombre, esperado) => {
        const r = reconocerTipo(cat, nombre, catalogo.tipos);
        expect(r.subtipo).toBe(esperado);
        expect(r.confianza).toBe('alta');
    });

    it('no reconoce nombres ajenos y no cruza categorías', () => {
        expect(reconocerTipo('hardware', 'Mesa de trabajo', catalogo.tipos).confianza).toBe('ninguna');
        expect(reconocerTipo('software', 'Computadora Dell', catalogo.tipos).subtipo).toBeNull();
    });

    it('marca confianza media cuando el nombre es ambiguo', () => {
        const r = reconocerTipo('hardware', 'Monitor y CPU', catalogo.tipos);
        expect(r.confianza).toBe('media');
        expect(r.candidatos.length).toBeGreaterThan(1);
    });
});

function montar(tipo, nombre) {
    const modelo = reactive(modeloVacio());
    const wrapper = mount(FichaTecnicaActivo, {
        props: { tipo, nombre, catalogo, modelo },
        global: { stubs: { Transition: false, TransitionGroup: false } },
    });
    return { wrapper, modelo };
}
const textoSecciones = (w) => w.findAll('.seccion-ficha__cabecera').map((b) => b.text()).join(' | ');
async function abrirTodo(w) {
    for (const b of w.findAll('.seccion-ficha__cabecera')) {
        if (b.attributes('aria-expanded') === 'false') await b.trigger('click');
    }
}

describe('formulario dinámico de ficha técnica', () => {
    it('escenario 1: «Computadora Dell» muestra los campos de computadora', async () => {
        const { wrapper, modelo } = montar('hardware', 'Computadora Dell');
        await nextTick();
        expect(modelo.subtipo).toBe('computadora');
        expect(wrapper.text()).toContain('Reconocido');
        expect(textoSecciones(wrapper)).toContain('Características técnicas');
        await abrirTodo(wrapper);
        expect(wrapper.text()).toContain('Memoria RAM (GB)');
        expect(wrapper.text()).toContain('Antivirus instalado');
    });

    it('escenarios 2 y 9: cambiar a «Laptop HP» actualiza el tipo sin perder lo escrito', async () => {
        const { wrapper, modelo } = montar('hardware', 'Computadora Dell');
        await nextTick();
        modelo.tecnico.procesador = 'Intel Core i7';
        modelo.tecnico.numero_serie = 'ABC123';
        await wrapper.setProps({ nombre: 'Laptop HP' });
        await nextTick();
        expect(modelo.subtipo).toBe('laptop');
        expect(modelo.tecnico.procesador).toBe('Intel Core i7');
        expect(tecnicoParaEnviar(modelo, catalogo).numero_serie).toBe('ABC123');
    });

    it('escenarios 3 y 4: cámara e impresora 3D muestran solo sus campos', async () => {
        const camara = montar('hardware', 'Cámara Hikvision');
        await nextTick(); await abrirTodo(camara.wrapper);
        expect(camara.wrapper.text()).toContain('Tipo de cámara');
        expect(camara.wrapper.text()).not.toContain('Memoria RAM');

        const i3d = montar('hardware', 'Impresora 3D Creality');
        await nextTick(); await abrirTodo(i3d.wrapper);
        expect(i3d.modelo.subtipo).toBe('impresora_3d');
        expect(i3d.wrapper.text()).toContain('Volumen de impresión');
        expect(i3d.wrapper.text()).toContain('Materiales compatibles');
    });

    it('escenario 5: un PLC solo pide IP cuando tiene conexión de red', async () => {
        const { wrapper, modelo } = montar('hardware', 'PLC Siemens');
        await nextTick(); await abrirTodo(wrapper);
        expect(wrapper.text()).toContain('Protocolos de comunicación');
        expect(wrapper.text()).not.toContain('Dirección IP');
        modelo.tecnico.conectividad = 'ethernet';
        await nextTick();
        expect(wrapper.text()).toContain('Dirección IP');
        modelo.tecnico.direccion_ip = '10.0.0.2';
        modelo.tecnico.conectividad = 'usb';
        expect(tecnicoParaEnviar(modelo, catalogo).direccion_ip).toBeNull(); // oculta → no se envía
    });

    it('escenarios 6 y 7: software Windows 11 y Microsoft Office', async () => {
        const so = montar('software', 'Windows 11');
        await nextTick(); await abrirTodo(so.wrapper);
        expect(so.modelo.subtipo).toBe('sistema_operativo');
        expect(so.wrapper.text()).toContain('Soporte del fabricante');
        expect(so.wrapper.text()).toContain('Tipo de licencia');

        const office = montar('software', 'Microsoft Office');
        await nextTick(); await abrirTodo(office.wrapper);
        expect(office.modelo.subtipo).toBe('productividad');
        expect(office.wrapper.text()).toContain('Licencias adquiridas');
        expect(office.wrapper.text()).not.toContain('Soporte del fabricante');
    });

    it('escenario 8: un nombre no reconocido permite continuar sin tipo', async () => {
        const { wrapper, modelo } = montar('hardware', 'Mesa de trabajo');
        await nextTick();
        expect(modelo.subtipo).toBeNull();
        expect(wrapper.text()).toContain('Sin tipo específico');
        expect(wrapper.findAll('.seccion-ficha')).toHaveLength(0);
        expect(tecnicoParaEnviar(modelo, catalogo)).toEqual({});
    });

    it('con confianza media pide confirmación antes de mostrar campos', async () => {
        const { wrapper, modelo } = montar('hardware', 'Monitor y CPU');
        await nextTick();
        expect(modelo.subtipo).toBeNull();
        expect(wrapper.text()).toContain('Sí, confirmar');
        await wrapper.find('.sugerencia__si').trigger('click');
        await nextTick();
        expect(modelo.subtipo).not.toBeNull();
        expect(modelo.manual).toBe(true);
    });

    it('al editar respeta el tipo guardado y sugiere sin imponer si el nombre cambia', async () => {
        const modelo = reactive({ ...modeloVacio(), subtipo: 'servidor', manual: true, tecnico: { raid: 'raid10' } });
        const wrapper = mount(FichaTecnicaActivo, { props: { tipo: 'hardware', nombre: 'Equipo principal', catalogo, modelo }, global: { stubs: { Transition: false, TransitionGroup: false } } });
        await nextTick();
        expect(modelo.subtipo).toBe('servidor');
        await wrapper.setProps({ nombre: 'Laptop del director' });
        await nextTick();
        expect(modelo.subtipo).toBe('servidor');
        expect(wrapper.text()).toContain('El nombre sugiere');
        expect(modelo.tecnico.raid).toBe('raid10');
    });
});
