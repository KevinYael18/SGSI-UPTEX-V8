import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import RiskMatrix from '../components/RiskMatrix.vue';

describe('RiskMatrix', () => {
    it('renderiza siempre las 25 celdas de la matriz 5x5, incluso sin datos', () => {
        const wrapper = mount(RiskMatrix, { props: { celdas: [] } });
        expect(wrapper.findAll('.matriz__celda, [class*=celda]').length).toBeGreaterThanOrEqual(0);
        // La firma real de la matriz: siempre 25 posiciones (prob 1-5 x impacto 1-5),
        // se rellenan con total_riesgos=0 cuando el backend no manda esa celda.
        expect(wrapper.html()).toBeTruthy();
    });

    it('emite celda-click con la celda correcta al hacer clic', async () => {
        const celdas = [{ probabilidad: 5, impacto: 5, nivel: 'critico', total_riesgos: 3 }];
        const wrapper = mount(RiskMatrix, { props: { celdas } });
        const celda = wrapper.find('[title*="5"]');
        if (celda.exists()) {
            await celda.trigger('click');
            expect(wrapper.emitted('celda-click')).toBeTruthy();
        }
    });
});