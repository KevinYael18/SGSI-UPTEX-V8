/**
 * Markdown mínimo y SEGURO para respuestas de SGSI Intelligence Assistant.
 *
 * 1. Se escapa TODO el HTML primero (la salida del modelo nunca se interpreta
 *    como HTML: mitiga XSS por inyección indirecta — OWASP LLM05).
 * 2. Luego se aplica un subconjunto fijo: encabezados (#, ##, ###), listas
 *    (- , * , 1. ), **negritas**, `código`, citas [Fuente N] y párrafos.
 * 3. No se generan enlaces: una URL sugerida por el modelo se muestra como
 *    texto, nunca como hipervínculo clicable.
 */
const ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

export function escaparHtml(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, (c) => ESCAPES[c]);
}

function enLinea(texto) {
    return texto
        .replace(/`([^`\n]+)`/g, '<code>$1</code>')
        .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\[(Fuente \d{1,2})\]/g, '<span class="ia-cita">$1</span>');
}

export function renderizarMarkdown(texto) {
    const lineas = escaparHtml(texto).replace(/\r\n?/g, '\n').split('\n');
    const html = [];
    let lista = null; // 'ul' | 'ol'
    let parrafo = [];

    const cerrarParrafo = () => {
        if (parrafo.length) {
            html.push(`<p>${parrafo.map(enLinea).join('<br>')}</p>`);
            parrafo = [];
        }
    };
    const cerrarLista = () => {
        if (lista) {
            html.push(`</${lista}>`);
            lista = null;
        }
    };

    for (const cruda of lineas) {
        const linea = cruda.trimEnd();
        const encabezado = linea.match(/^\s{0,3}(#{1,4})\s+(.+)$/);
        const vineta = linea.match(/^\s*[-*•]\s+(.+)$/);
        const numerada = linea.match(/^\s*\d{1,2}[.)]\s+(.+)$/);

        if (!linea.trim()) {
            cerrarParrafo();
            cerrarLista();
        } else if (encabezado) {
            cerrarParrafo();
            cerrarLista();
            html.push(`<h4>${enLinea(encabezado[2])}</h4>`);
        } else if (vineta || numerada) {
            cerrarParrafo();
            const tipo = vineta ? 'ul' : 'ol';
            if (lista !== tipo) {
                cerrarLista();
                html.push(`<${tipo}>`);
                lista = tipo;
            }
            html.push(`<li>${enLinea((vineta || numerada)[1])}</li>`);
        } else {
            cerrarLista();
            parrafo.push(linea.trim());
        }
    }
    cerrarParrafo();
    cerrarLista();

    return html.join('');
}
