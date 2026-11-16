<template>
  <div class="ficha-dinamica" :class="{ 'ficha-dinamica--activa': subtipo }">
    <!-- Indicador del tipo reconocido -->
    <div class="ficha-dinamica__reconocimiento" aria-live="polite">
      <Transition name="aviso" mode="out-in">
        <div v-if="subtipo" key="tipo" class="chip-tipo">
          <AppIcon name="check" :size="14" :grosor="3" />
          <span><small>{{ manual ? 'Tipo de activo' : 'Reconocido' }}:</small> <strong>{{ etiquetaDe(subtipo) }}</strong></span>
          <button type="button" class="chip-tipo__accion" @click="eligiendo = !eligiendo">Cambiar</button>
        </div>
        <div v-else-if="reconocimiento.confianza === 'media'" key="sugerencia" class="sugerencia">
          <span>¿Es {{ articulo(reconocimiento.candidatos[0].etiqueta) }} <strong>{{ reconocimiento.candidatos[0].etiqueta }}</strong>?</span>
          <button type="button" class="sugerencia__si" @click="elegir(reconocimiento.candidatos[0].subtipo)">Sí, confirmar</button>
          <button v-for="c in reconocimiento.candidatos.slice(1)" :key="c.subtipo" type="button" class="sugerencia__otra" @click="elegir(c.subtipo)">{{ c.etiqueta }}</button>
          <button type="button" class="sugerencia__otra" @click="eligiendo = true">Otro…</button>
        </div>
        <div v-else-if="hayTiposEnCategoria && nombre.trim().length >= 3" key="ninguno" class="sin-tipo">
          <span>Sin tipo específico: se registrará con los datos generales.</span>
          <button type="button" class="chip-tipo__accion" @click="eligiendo = !eligiendo">Elegir un tipo</button>
        </div>
      </Transition>

      <Transition name="aviso">
        <p v-if="sugerenciaDistinta" class="pista">
          El nombre sugiere «{{ etiquetaDe(reconocimiento.subtipo) }}».
          <button type="button" @click="elegir(reconocimiento.subtipo)">Usar ese tipo</button>
        </p>
      </Transition>

      <Transition name="aviso">
        <div v-if="eligiendo" class="selector-tipo">
          <AppSelect :model-value="subtipo || ''" label="Tipo de activo" placeholder="Sin tipo específico"
            :opciones="[{ value: '', label: 'Sin tipo específico (solo datos generales)' }, ...opcionesTipo]"
            @update:model-value="(v) => elegir(v || null)" />
        </div>
      </Transition>
    </div>

    <!-- Campos técnicos del tipo -->
    <TransitionGroup name="seccion" tag="div" class="ficha-dinamica__secciones">
      <section v-for="(sec, i) in secciones" :key="`${subtipo}-${sec.titulo}`" class="seccion-ficha">
        <button type="button" class="seccion-ficha__cabecera" :aria-expanded="abiertas.has(i)" @click="alternar(i)">
          <span>{{ sec.titulo }}</span>
          <small v-if="llenos(sec)" class="seccion-ficha__conteo">{{ llenos(sec) }} de {{ visibles(sec).length }}</small>
          <AppIcon name="chevron-abajo" :size="16" class="seccion-ficha__chevron" />
        </button>
        <div v-show="abiertas.has(i)" class="seccion-ficha__cuerpo">
          <div class="form__grid">
            <template v-for="campo in visibles(sec)" :key="campo.clave">
              <label v-if="campo.tipo === 'textarea'" class="app-field campo-ancho">
                <span class="app-field__label">{{ campo.etiqueta }}</span>
                <textarea v-model="tecnico[campo.clave]" :maxlength="campo.max || 1000" rows="2" />
                <span v-if="error(campo.clave)" class="app-field__error" role="alert">{{ error(campo.clave) }}</span>
                <span v-else-if="campo.ayuda" class="app-field__ayuda">{{ campo.ayuda }}</span>
              </label>
              <div v-else-if="campo.tipo === 'select'" class="campo">
                <AppSelect v-model="tecnico[campo.clave]" :label="campo.etiqueta" placeholder="Sin especificar"
                  :opciones="[{ value: '', label: 'Sin especificar' }, ...campo.opciones]" />
                <span v-if="error(campo.clave)" class="app-field__error" role="alert">{{ error(campo.clave) }}</span>
              </div>
              <AppInput v-else v-model="tecnico[campo.clave]" :label="campo.etiqueta" :type="tipoInput(campo)"
                :min="campo.min" :max="campo.maxv" :maxlength="campo.max" :placeholder="marcador(campo)"
                :error="error(campo.clave)" :ayuda="campo.ayuda || ''" />
            </template>
          </div>
        </div>
      </section>
    </TransitionGroup>

    <!-- Características personalizadas (para cualquier activo) -->
    <div class="personalizados">
      <div class="personalizados__cabecera">
        <span class="app-field__label">Características adicionales</span>
        <button type="button" class="chip-tipo__accion" :disabled="personalizados.length >= 30" @click="personalizados.push({ nombre: '', valor: '' })">+ Agregar</button>
      </div>
      <TransitionGroup name="aviso" tag="div" class="personalizados__lista">
        <div v-for="(p, i) in personalizados" :key="i" class="personalizados__fila">
          <AppInput v-model="p.nombre" placeholder="Característica (ej. Voltaje)" maxlength="60" aria-label="Nombre de la característica" />
          <AppInput v-model="p.valor" placeholder="Valor (ej. 127 V)" maxlength="255" aria-label="Valor de la característica" />
          <button type="button" class="personalizados__quitar" :aria-label="`Quitar ${p.nombre || 'característica'}`" @click="personalizados.splice(i, 1)">
            <AppIcon name="cerrar" :size="15" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import AppInput from '../ui/AppInput.vue';
import AppSelect from '../ui/AppSelect.vue';
import AppIcon from '../ui/AppIcon.vue';
import { reconocerTipo, seccionesDe, campoVisible } from '../../composables/useReconocimientoActivo';

/**
 * Ficha técnica dinámica del activo. El estado vive en `modelo` (objeto
 * reactivo del padre): { subtipo, manual, tecnico: {}, personalizados: [] }.
 * Los valores de `tecnico` NUNCA se borran al cambiar de nombre o de tipo;
 * al enviar, el padre usa tecnicoParaEnviar() para mandar solo los del tipo actual.
 */
const props = defineProps({
    tipo: { type: String, default: '' },        // clasificación general
    nombre: { type: String, default: '' },
    catalogo: { type: Object, default: null },  // GET /activos/tipos-tecnicos
    modelo: { type: Object, required: true },
    errores: { type: Object, default: () => ({}) },
});

const eligiendo = ref(false);
const abiertas = ref(new Set([0]));
const tecnico = computed(() => props.modelo.tecnico);
const personalizados = computed(() => props.modelo.personalizados);
const manual = computed(() => !!props.modelo.manual);

const tipos = computed(() => props.catalogo?.tipos || []);
const hayTiposEnCategoria = computed(() => tipos.value.some((t) => t.categoria === props.tipo));
const opcionesTipo = computed(() => tipos.value.filter((t) => t.categoria === props.tipo).map((t) => ({ value: t.clave, label: t.etiqueta })));
const reconocimiento = computed(() => reconocerTipo(props.tipo, props.nombre, tipos.value));

// Tipo efectivo: el elegido por el usuario o, si no, el reconocido con confianza alta.
const subtipo = computed(() => {
    if (manual.value) {
        const t = tipos.value.find((x) => x.clave === props.modelo.subtipo);
        return t && t.categoria === props.tipo ? props.modelo.subtipo : null;
    }
    return reconocimiento.value.confianza === 'alta' ? reconocimiento.value.subtipo : null;
});
watch(subtipo, (nuevo, anterior) => {
    props.modelo.subtipo = nuevo;
    if (nuevo !== anterior) abiertas.value = new Set([0]);
}, { immediate: true });

const sugerenciaDistinta = computed(() => manual.value && reconocimiento.value.confianza === 'alta'
    && reconocimiento.value.subtipo && reconocimiento.value.subtipo !== subtipo.value);

const secciones = computed(() => (subtipo.value ? seccionesDe(subtipo.value, props.catalogo) : []));

function elegir(clave) {
    props.modelo.subtipo = clave;
    props.modelo.manual = true;
    eligiendo.value = false;
}
function etiquetaDe(clave) {
    return tipos.value.find((t) => t.clave === clave)?.etiqueta || clave;
}
function articulo(etiqueta) {
    return /^(impresora|c[aá]mara|computadora|laptop|pantalla|maquinaria)/i.test(etiqueta) ? 'una' : 'un';
}
function alternar(i) {
    const s = new Set(abiertas.value);
    if (s.has(i)) s.delete(i); else s.add(i);
    abiertas.value = s;
}
function visibles(sec) {
    return sec.campos.filter((c) => campoVisible(c, tecnico.value));
}
function llenos(sec) {
    return visibles(sec).filter((c) => tecnico.value[c.clave] !== undefined && tecnico.value[c.clave] !== '' && tecnico.value[c.clave] !== null).length;
}
function tipoInput(campo) {
    return { entero: 'number', numero: 'number', fecha: 'date' }[campo.tipo] || 'text';
}
function marcador(campo) {
    return { ip: '192.168.0.10', mac: '00:1A:2B:3C:4D:5E' }[campo.tipo] || '';
}
function error(clave) {
    return props.errores?.[`tecnico.${clave}`]?.[0] || '';
}
</script>

<script>
import { seccionesDe as _secciones, campoVisible as _visible } from '../../composables/useReconocimientoActivo';

/** Solo los campos del tipo actual (los ocultos por condición se envían vacíos). */
export function tecnicoParaEnviar(modelo, catalogo) {
    if (!modelo.subtipo) return {};
    const salida = {};
    for (const sec of _secciones(modelo.subtipo, catalogo)) {
        for (const c of sec.campos) {
            const v = modelo.tecnico[c.clave];
            salida[c.clave] = !_visible(c, modelo.tecnico) || v === '' || v === undefined ? null : v;
        }
    }
    return salida;
}

export function personalizadosParaEnviar(modelo) {
    return (modelo.personalizados || []).filter((p) => (p.nombre || '').trim() || (p.valor || '').trim());
}

export function modeloVacio() {
    return { subtipo: null, manual: false, tecnico: {}, personalizados: [] };
}
</script>

<style scoped>
.ficha-dinamica { display: flex; flex-direction: column; gap: var(--space-3); }
.ficha-dinamica__reconocimiento { display: flex; flex-direction: column; gap: 8px; min-height: 0; }
.chip-tipo {
    display: inline-flex; align-self: flex-start; align-items: center; gap: 8px; flex-wrap: wrap; padding: 6px 8px 6px 12px;
    border-radius: 999px; background: var(--c-primary-soft); color: var(--c-primary); font-size: var(--text-sm);
    border: 1px solid color-mix(in srgb, var(--c-primary) 25%, transparent);
}
.chip-tipo small { color: var(--text-muted); font-weight: 500; }
.chip-tipo__accion {
    border: none; background: var(--surface); color: var(--c-signal); font-weight: 600; font-size: var(--text-xs);
    padding: 4px 10px; border-radius: 999px; cursor: pointer; transition: background var(--transition-fast), transform var(--transition-fast);
}
.chip-tipo__accion:hover { background: var(--c-primary); color: var(--on-primary); }
.chip-tipo__accion:active { transform: scale(0.95); }
.chip-tipo__accion:disabled { opacity: 0.5; cursor: not-allowed; }
.sugerencia, .sin-tipo {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 8px 12px; border-radius: var(--radius-md);
    font-size: var(--text-sm); background: var(--bg-subtle); color: var(--text-muted);
}
.sugerencia { background: var(--c-amber-soft); color: var(--text); }
.sugerencia button {
    border: 1px solid var(--border-strong); background: var(--surface); color: var(--text); border-radius: 999px;
    padding: 3px 10px; font-size: var(--text-xs); font-weight: 600; cursor: pointer; transition: border-color var(--transition-fast), color var(--transition-fast);
}
.sugerencia .sugerencia__si { background: var(--c-primary); border-color: var(--c-primary); color: var(--on-primary); }
.sugerencia button:hover { border-color: var(--c-primary); }
.pista { margin: 0; font-size: var(--text-xs); color: var(--text-muted); }
.pista button { border: none; background: none; color: var(--c-signal); font-weight: 600; cursor: pointer; padding: 0 4px; }
.selector-tipo { max-width: 420px; }

.ficha-dinamica__secciones { display: flex; flex-direction: column; gap: 8px; }
.seccion-ficha { border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--surface); overflow: hidden; }
.seccion-ficha__cabecera {
    width: 100%; display: flex; align-items: center; gap: 10px; padding: 10px 14px; border: none; background: var(--bg-subtle);
    color: var(--text); font-weight: 600; font-size: var(--text-sm); cursor: pointer; text-align: left;
    transition: background var(--transition-fast);
}
.seccion-ficha__cabecera:hover { background: var(--surface-hover); }
.seccion-ficha__cabecera > span { flex: 1; }
.seccion-ficha__conteo { font-weight: 500; color: var(--c-success); }
.seccion-ficha__chevron { transition: transform var(--transition-base); color: var(--text-subtle); }
.seccion-ficha__cabecera[aria-expanded='true'] .seccion-ficha__chevron { transform: rotate(180deg); }
.seccion-ficha__cuerpo { padding: var(--space-4); animation: entrar-arriba 220ms var(--ease-salida, var(--ease)) backwards; }
.campo { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.campo-ancho { grid-column: 1 / -1; }

.personalizados { display: flex; flex-direction: column; gap: 8px; }
.personalizados__cabecera { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.personalizados__lista { display: flex; flex-direction: column; gap: 8px; }
.personalizados__fila { display: grid; grid-template-columns: 1fr 1fr 38px; gap: 8px; align-items: center; }
.personalizados__quitar {
    display: grid; place-items: center; width: 38px; height: 38px; border-radius: var(--radius-sm); border: 1px solid var(--border);
    background: var(--surface); color: var(--text-muted); cursor: pointer; transition: color var(--transition-fast), border-color var(--transition-fast);
}
.personalizados__quitar:hover { color: var(--c-risk); border-color: var(--c-risk); }

.seccion-enter-active { transition: opacity 220ms var(--ease), transform 220ms var(--ease); }
.seccion-leave-active { transition: opacity 120ms var(--ease); }
.seccion-enter-from { opacity: 0; transform: translateY(6px); }
.seccion-leave-to { opacity: 0; }
@media (max-width: 640px) {
    .form__grid { grid-template-columns: 1fr; }
    .personalizados__fila { grid-template-columns: 1fr 38px; }
    .personalizados__fila > :first-child { grid-column: 1 / -1; }
}
</style>
