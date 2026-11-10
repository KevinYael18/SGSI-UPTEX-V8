import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import AppBadge from '../components/ui/AppBadge.vue';

describe('AppBadge', () => {
    it('renderiza el contenido del slot', () => {
        const wrapper = mount(AppBadge, { slots: { default: 'Crítico' } });
        expect(wrapper.text()).toBe('Crítico');
    });

    it('aplica la clase del tono recibido', () => {
        const wrapper = mount(AppBadge, { props: { tono: 'riesgo' }, slots: { default: 'x' } });
        expect(wrapper.classes().some((c) => c.includes('riesgo'))).toBe(true);
    });
});