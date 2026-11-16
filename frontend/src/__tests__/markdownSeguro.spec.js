import { describe, it, expect } from 'vitest';
import { renderizarMarkdown } from '../utils/markdownSeguro';

describe('renderizarMarkdown (respuestas de SGSI Intelligence Assistant)', () => {
    it('escapa HTML y scripts de la respuesta del modelo', () => {
        const html = renderizarMarkdown('<script>alert(1)</script><img src=x onerror=alert(2)>');
        expect(html).not.toContain('<script>');
        expect(html).not.toContain('<img');
        expect(html).toContain('&lt;script&gt;');
    });

    it('no genera enlaces clicables', () => {
        const html = renderizarMarkdown('Visita [aquí](https://malicioso.example) o https://otro.example');
        expect(html).not.toContain('<a ');
        expect(html).not.toContain('href');
    });

    it('da formato a encabezados, listas, negritas y citas de fuentes', () => {
        const html = renderizarMarkdown('### Controles\n- **A.8.13** respaldo [Fuente 1]\n- A.5.15\n\n1. Uno\n2. Dos');
        expect(html).toContain('<h4>Controles</h4>');
        expect(html).toContain('<ul><li><strong>A.8.13</strong> respaldo <span class="ia-cita">Fuente 1</span></li><li>A.5.15</li></ul>');
        expect(html).toContain('<ol><li>Uno</li><li>Dos</li></ol>');
    });
});
