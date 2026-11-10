import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import AppTable from '../components/ui/AppTable.vue';

const columnas = [{ key: 'nombre', label: 'Nombre' }];

describe('AppTable', () => {
    it('muestra el estado de carga sin mezclar filas previas', () => {
        const wrapper = mount(AppTable, {
            props: { columnas, filas: [{ id: 1, nombre: 'Fila vieja' }], cargando: true },
        });
        // Bug real encontrado y corregido: v-for + v-else en el mismo <tr>
        // hacía que las filas previas siguieran visibles durante la carga.
        expect(wrapper.text()).toContain('Cargando');
        expect(wrapper.text()).not.toContain('Fila vieja');
    });

    it('muestra el slot vacío cuando no hay filas y no está cargando', () => {
        const wrapper = mount(AppTable, {
            props: { columnas, filas: [], cargando: false },
            slots: { vacio: 'Nada por aquí' },
        });
        expect(wrapper.text()).toContain('Nada por aquí');
    });

    it('renderiza una fila por cada elemento y emite fila-click si es clicable', async () => {
        const filas = [{ id: 1, nombre: 'Activo A' }, { id: 2, nombre: 'Activo B' }];
        const wrapper = mount(AppTable, { props: { columnas, filas, cargando: false, clicable: true } });
        const tbodyRows = wrapper.findAll('tbody tr');
        expect(tbodyRows).toHaveLength(2);
        await tbodyRows[0].trigger('click');
        expect(wrapper.emitted('fila-click')[0][0]).toEqual(filas[0]);
    });

    it('muestra los controles de paginación solo cuando hay más de una página', () => {
        const conUnaPagina = mount(AppTable, {
            props: { columnas, filas: [], paginacion: { current_page: 1, last_page: 1, per_page: 15, total: 3 } },
        });
        expect(conUnaPagina.find('.app-table__paginacion').exists()).toBe(false);

        const conVariasPaginas = mount(AppTable, {
            props: { columnas, filas: [], paginacion: { current_page: 2, last_page: 5, per_page: 15, total: 70 } },
        });
        expect(conVariasPaginas.find('.app-table__paginacion').exists()).toBe(true);
        expect(conVariasPaginas.text()).toContain('Página 2 de 5');
    });

    it('deshabilita "Anterior" en la primera página y "Siguiente" en la última', () => {
        const wrapper = mount(AppTable, {
            props: { columnas, filas: [], paginacion: { current_page: 1, last_page: 3, per_page: 10, total: 30 } },
        });
        const botones = wrapper.findAll('.app-table__pag-btn');
        expect(botones[0].attributes('disabled')).toBeDefined();
        expect(botones[1].attributes('disabled')).toBeUndefined();
    });
});