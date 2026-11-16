<template>
  <div class="risk-matrix">
    <!-- Una sola cuadrícula: etiquetas de ejes y celdas comparten filas/columnas,
         así los números siempre quedan alineados con su celda. -->
    <div class="risk-matrix__grid">
      <div class="risk-matrix__eje-y" aria-hidden="true">Impacto</div>

      <template v-for="impacto in [5, 4, 3, 2, 1]" :key="`fila-${impacto}`">
        <span class="risk-matrix__num risk-matrix__num--y" :style="{ gridRow: 6 - impacto }" aria-hidden="true">{{ impacto }}</span>
        <button
          v-for="probabilidad in 5"
          :key="`${probabilidad}-${impacto}`"
          type="button"
          class="risk-matrix__celda"
          :class="claseCelda(celda(probabilidad, impacto))"
          :style="{ gridRow: 6 - impacto, gridColumn: probabilidad + 2, '--i': (5 - impacto) + (probabilidad - 1) }"
          :title="titulo(celda(probabilidad, impacto))"
          :aria-label="titulo(celda(probabilidad, impacto))"
          @click="$emit('celda-click', celda(probabilidad, impacto))"
        >
          <span class="risk-matrix__conteo">{{ celda(probabilidad, impacto).total_riesgos || '' }}</span>
        </button>
      </template>

      <span
        v-for="probabilidad in 5"
        :key="`x-${probabilidad}`"
        class="risk-matrix__num risk-matrix__num--x"
        :style="{ gridColumn: probabilidad + 2 }"
        aria-hidden="true"
      >{{ probabilidad }}</span>
      <div class="risk-matrix__eje-x" aria-hidden="true">Probabilidad →</div>
    </div>

    <ul class="risk-matrix__leyenda" aria-label="Niveles de riesgo">
      <li><span class="risk-matrix__muestra risk-matrix__celda--bajo" />Bajo</li>
      <li><span class="risk-matrix__muestra risk-matrix__celda--medio" />Medio</li>
      <li><span class="risk-matrix__muestra risk-matrix__celda--alto" />Alto</li>
      <li><span class="risk-matrix__muestra risk-matrix__celda--critico" />Crítico</li>
    </ul>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ celdas: { type: Array, default: () => [] } });
defineEmits(['celda-click']);

// El backend entrega 25 celdas (probabilidad 1-5 × impacto 1-5). Se indexan
// por "p-i" y se dibujan con impacto descendente (5 arriba), como una matriz
// de riesgo convencional. Si faltara alguna celda, se usa una vacía.
const porClave = computed(() => {
    const mapa = {};
    for (const c of props.celdas) mapa[`${c.probabilidad}-${c.impacto}`] = c;
    return mapa;
});

function celda(probabilidad, impacto) {
    return porClave.value[`${probabilidad}-${impacto}`] ?? { probabilidad, impacto, nivel: null, total_riesgos: 0 };
}

function claseCelda(c) {
    return [`risk-matrix__celda--${c.nivel || 'sin-datos'}`, { 'risk-matrix__celda--con-riesgos': c.total_riesgos > 0 }];
}

function titulo(c) {
    const nivel = c.nivel ? ` (nivel ${c.nivel})` : '';
    return `Probabilidad ${c.probabilidad} × Impacto ${c.impacto}${nivel}: ${c.total_riesgos} riesgo(s)`;
}
</script>

<style scoped>
.risk-matrix { width: 100%; max-width: 540px; margin: 0 auto; }

.risk-matrix__grid {
    display: grid;
    grid-template-columns: 20px 22px repeat(5, minmax(0, 1fr));
    grid-template-rows: repeat(5, auto) auto auto;
    gap: 6px;
}

.risk-matrix__eje-y {
    grid-column: 1; grid-row: 1 / 6; align-self: center;
    writing-mode: vertical-rl; transform: rotate(180deg);
    font-size: var(--text-xs); font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted);
}
.risk-matrix__num {
    display: grid; place-items: center;
    font-size: var(--text-sm); font-weight: 600; color: var(--text-subtle); font-family: var(--font-mono);
}
.risk-matrix__num--y { grid-column: 2; }
.risk-matrix__num--x { grid-row: 6; padding-top: 2px; }
.risk-matrix__eje-x {
    grid-column: 3 / 8; grid-row: 7; text-align: center;
    font-size: var(--text-xs); font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted);
}

.risk-matrix__celda {
    --tono: var(--c-gray-400);
    --fondo: var(--bg-subtle);
    /* El ancho lo da la columna (1fr) y el alto se deriva de él: celdas siempre cuadradas. */
    width: 100%; aspect-ratio: 1; min-height: 0;
    position: relative; border: none; padding: 0; font: inherit;
    border-radius: 10px; display: flex; align-items: center; justify-content: center;
    background: var(--fondo); color: var(--tono);
    font-weight: 700; cursor: pointer;
    transition: transform var(--transition-fast), box-shadow var(--transition-fast), filter var(--transition-fast);
}
.risk-matrix__celda:hover { transform: scale(1.05); box-shadow: var(--shadow-md); z-index: 1; }

/* ---- Movimiento (solo presentación) ----
   Entrada en ola diagonal desde la esquina de mayor riesgo. `backwards`
   para que, al terminar, el hover (scale) siga funcionando. */
.risk-matrix__celda {
    animation: celda-entrar 420ms var(--ease-salida, var(--ease)) backwards;
    animation-delay: calc(var(--i, 0) * 38ms);
}
.risk-matrix__celda:active { transform: scale(0.97); transition-duration: 90ms; }
@keyframes celda-entrar {
    from { opacity: 0; transform: scale(0.6); }
    to { opacity: 1; transform: scale(1); }
}
/* Celdas críticas con riesgos: un anillo que pulsa DOS veces tras la entrada
   (no en bucle) para dirigir la atención sin cambiar color ni valor. */
.risk-matrix__celda--critico.risk-matrix__celda--con-riesgos::after {
    content: ''; position: absolute; inset: 0; border-radius: inherit; pointer-events: none;
    box-shadow: 0 0 0 2px var(--tono); opacity: 0;
    animation: celda-alerta 1.3s var(--ease) 2;
    animation-delay: calc(var(--i, 0) * 38ms + 520ms);
}
@keyframes celda-alerta {
    0% { opacity: 0.75; transform: scale(1); }
    100% { opacity: 0; transform: scale(1.32); }
}
.risk-matrix__conteo { position: relative; z-index: 1; }
@media (prefers-reduced-motion: reduce) {
    .risk-matrix__celda, .risk-matrix__celda--critico.risk-matrix__celda--con-riesgos::after { animation: none; }
}
.risk-matrix__celda:focus-visible { outline: 2px solid var(--c-primary); outline-offset: 2px; }
.risk-matrix__celda--bajo { --tono: var(--c-success); --fondo: var(--c-success-soft); }
.risk-matrix__celda--medio { --tono: var(--c-amber); --fondo: var(--c-amber-soft); }
.risk-matrix__celda--alto { --tono: var(--c-orange); --fondo: var(--c-orange-soft); }
.risk-matrix__celda--critico { --tono: var(--c-risk); --fondo: var(--c-risk-soft); }
.risk-matrix__celda--sin-datos { --fondo: var(--bg-subtle); }

/* Celdas con riesgos registrados: color sólido para que destaquen */
.risk-matrix__celda--con-riesgos { background: var(--tono); color: #fff; box-shadow: 0 6px 16px -6px var(--tono); }
[data-theme='dark'] .risk-matrix__celda--con-riesgos { color: #0F1214; }
.risk-matrix__conteo { font-family: var(--font-display); font-size: clamp(1rem, 0.8rem + 0.8vw, 1.45rem); line-height: 1; }

.risk-matrix__leyenda {
    display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 16px; list-style: none; margin: 16px 0 0; padding: 0;
    font-size: var(--text-sm); color: var(--text-muted);
}
.risk-matrix__leyenda li { display: inline-flex; align-items: center; gap: 6px; }
.risk-matrix__muestra { width: 14px; height: 14px; border-radius: 4px; background: var(--tono); }

@media (max-width: 640px) {
    .risk-matrix__grid { gap: 4px; grid-template-columns: 16px 18px repeat(5, minmax(0, 1fr)); }
    .risk-matrix__celda { border-radius: 8px; }
}
</style>
