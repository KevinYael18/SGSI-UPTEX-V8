<template>
  <div class="asis">
    <header class="asis__cabecera">
      <IaMarca tamano="lg" subtitulo="Asistente especializado en seguridad de la información e ISO/IEC 27001:2022" />
      <nav v-if="pestanas.length > 1" class="asis__pestanas" aria-label="Secciones del asistente">
        <button
          v-for="p in pestanas"
          :key="p.id"
          type="button"
          class="asis__pestana"
          :class="{ 'asis__pestana--activa': pestana === p.id }"
          :aria-current="pestana === p.id ? 'page' : undefined"
          @click="pestana = p.id"
        >
          <AppIcon :name="p.icono" :size="16" />{{ p.etiqueta }}
        </button>
      </nav>
    </header>

    <div v-if="estado && !estado.disponible" class="asis__aviso" role="alert">
      <AppIcon name="alerta" :size="16" />
      SGSI Intelligence Assistant no está configurado en este servidor. Un administrador debe definir la clave del proveedor en el archivo de entorno.
    </div>

    <!-- ===================== Conversación ===================== -->
    <section v-show="pestana === 'chat'" class="asis__chat" :class="{ 'asis__chat--historial': historialAbierto }">
      <aside class="asis__historial" aria-label="Historial de conversaciones">
        <AppButton icono="mas" style="width:100%" @click="nueva">Nueva conversación</AppButton>
        <p class="asis__historial-titulo"><AppIcon name="historial" :size="14" />Tus conversaciones</p>
        <p v-if="!conversaciones.length" class="asis__historial-vacio">Aún no tienes conversaciones. Solo tú puedes ver tu historial.</p>
        <ul class="asis__historial-lista">
          <li v-for="c in conversaciones" :key="c.uuid">
            <button
              type="button"
              class="asis__conv"
              :class="{ 'asis__conv--activa': c.uuid === actual?.uuid }"
              @click="abrir(c.uuid)"
            >
              <span class="asis__conv-titulo">{{ c.titulo }}</span>
              <span class="asis__conv-fecha">{{ fechaCorta(c.actualizada_en) }}</span>
            </button>
            <button type="button" class="asis__conv-borrar" :aria-label="`Eliminar «${c.titulo}»`" title="Eliminar conversación" @click="porEliminar = c">
              <AppIcon name="papelera" :size="15" />
            </button>
          </li>
        </ul>
      </aside>

      <div class="asis__principal">
        <div class="asis__barra-movil">
          <AppButton tamano="sm" variante="secundaria" icono="historial" @click="historialAbierto = !historialAbierto">
            {{ historialAbierto ? 'Ocultar historial' : 'Historial' }}
          </AppButton>
          <AppButton tamano="sm" variante="fantasma" icono="mas" @click="nueva">Nueva</AppButton>
        </div>

        <div ref="refMensajes" class="asis__mensajes" aria-live="polite">
          <div v-if="!mensajes.length && !enviando" class="asis__bienvenida">
            <span class="asis__bienvenida-icono"><AppIcon name="asistente" :size="34" grosor="1.7" /></span>
            <h2>¿En qué puedo ayudarte con la seguridad de la información?</h2>
            <p>Respondo sobre ISO/IEC 27001:2022, ciberseguridad, riesgos, incidentes, auditorías, activos y el uso de GSI UPTex, con los datos que tu rol puede consultar. No atiendo temas ajenos al SGSI.</p>
            <div class="asis__sugerencias">
              <button v-for="s in sugerencias" :key="s" type="button" class="asis__sugerencia" @click="enviar(s)">
                <AppIcon name="chispa" :size="15" />{{ s }}
              </button>
            </div>
          </div>

          <article
            v-for="m in mensajes"
            :key="m.id"
            class="asis__msg"
            :class="[`asis__msg--${m.rol}`, `asis__msg--${m.estado}`]"
          >
            <span v-if="m.rol === 'asistente'" class="asis__avatar" aria-hidden="true"><AppIcon name="asistente" :size="16" grosor="2" /></span>
            <div class="asis__burbuja">
              <span class="sr-only">{{ m.rol === 'usuario' ? 'Tú' : 'SGSI Intelligence Assistant' }}:</span>
              <p v-if="m.estado === 'rechazado' && m.rol === 'asistente'" class="asis__etiqueta-estado"><AppIcon name="info" :size="13" />Fuera del dominio del asistente</p>
              <p v-if="m.estado === 'bloqueado' && m.rol === 'asistente'" class="asis__etiqueta-estado"><AppIcon name="candado" :size="13" />Solicitud bloqueada por seguridad</p>
              <div v-if="m.rol === 'asistente'" class="ia-markdown" v-html="renderizarMarkdown(m.contenido)" />
              <p v-else class="asis__texto-usuario">{{ m.contenido }}</p>
              <div v-if="m.fuentes?.length" class="asis__fuentes">
                <span class="asis__fuentes-titulo">Fuentes:</span>
                <span v-for="(f, i) in m.fuentes" :key="i" class="asis__fuente" :class="`asis__fuente--${f.tipo}`" :title="f.categoria || ''">
                  <AppIcon :name="f.tipo === 'documento' ? 'libro' : f.tipo === 'guia' ? 'info' : 'dashboard'" :size="12" />
                  <template v-if="f.etiqueta">{{ f.etiqueta }}: </template>{{ f.titulo }}
                </span>
              </div>
            </div>
          </article>

          <div v-if="enviando" class="asis__msg asis__msg--asistente">
            <span class="asis__avatar" aria-hidden="true"><AppIcon name="asistente" :size="16" grosor="2" /></span>
            <IaProcesando texto="Consultando información autorizada y redactando la respuesta…" />
          </div>
          <div v-if="error" class="asis__error" role="alert"><AppIcon name="alerta" :size="16" />{{ error }}</div>
        </div>

        <form class="asis__composer" @submit.prevent="enviar()">
          <label for="asis-mensaje" class="sr-only">Escribe tu pregunta</label>
          <textarea
            id="asis-mensaje"
            ref="refEntrada"
            v-model="borrador"
            rows="1"
            :maxlength="maxCaracteres"
            :disabled="enviando || (estado && !estado.disponible)"
            placeholder="Pregunta sobre ISO 27001, riesgos, incidentes, auditorías o GSI UPTex…"
            @keydown.enter.exact.prevent="enviar()"
            @input="ajustarAltura"
          />
          <div class="asis__composer-pie">
            <span class="asis__contador" :class="{ 'asis__contador--limite': borrador.length > maxCaracteres * 0.9 }">{{ borrador.length }}/{{ maxCaracteres }}</span>
            <AppButton type="submit" icono="enviar" :cargando="enviando" :disabled="!borrador.trim()">Enviar</AppButton>
          </div>
        </form>
        <p class="asis__privacidad">
          <AppIcon name="candado" :size="12" />Tu historial es privado. No escribas contraseñas ni datos personales: si lo haces, se ocultan antes de enviarse. Verifica la información importante.
        </p>
      </div>
    </section>

    <IaConocimientoPanel v-if="pestana === 'conocimiento'" />
    <IaConsumoPanel v-if="pestana === 'consumo'" />

    <AppModal :abierto="!!porEliminar" titulo="Eliminar conversación" @cerrar="porEliminar = null">
      <p>¿Eliminar <strong>{{ porEliminar?.titulo }}</strong>? Ya no aparecerá en tu historial.</p>
      <template #footer>
        <AppButton variante="secundaria" @click="porEliminar = null">Cancelar</AppButton>
        <AppButton variante="peligro" :cargando="eliminando" @click="eliminar">Sí, eliminar</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import iaApi from '../../api/ia';
import { useToastStore } from '../../stores/toast';
import { renderizarMarkdown } from '../../utils/markdownSeguro';
import AppButton from '../../components/ui/AppButton.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import AppModal from '../../components/ui/AppModal.vue';
import IaMarca from '../../components/ia/IaMarca.vue';
import IaProcesando from '../../components/ia/IaProcesando.vue';
import IaConocimientoPanel from '../../components/ia/IaConocimientoPanel.vue';
import IaConsumoPanel from '../../components/ia/IaConsumoPanel.vue';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const estado = ref(null);
const pestana = ref('chat');
const conversaciones = ref([]);
const actual = ref(null);
const mensajes = ref([]);
const borrador = ref('');
const enviando = ref(false);
const error = ref('');
const historialAbierto = ref(false);
const porEliminar = ref(null);
const eliminando = ref(false);
const refMensajes = ref(null);
const refEntrada = ref(null);

const maxCaracteres = computed(() => estado.value?.max_caracteres_mensaje || 2000);
const pestanas = computed(() => [
    { id: 'chat', etiqueta: 'Conversación', icono: 'comentario' },
    ...(estado.value?.permisos?.conocimiento ? [{ id: 'conocimiento', etiqueta: 'Base de conocimiento', icono: 'libro' }] : []),
    ...(estado.value?.permisos?.administrar ? [{ id: 'consumo', etiqueta: 'Consumo', icono: 'tendencia' }] : []),
]);

// Preguntas sugeridas según los módulos que el rol puede consultar.
const sugerencias = computed(() => {
    const m = estado.value?.modulos || [];
    const lista = [];
    if (m.includes('dashboard')) lista.push('¿Cuál es el estado general del SGSI y qué debo atender primero?');
    if (m.includes('riesgos')) lista.push('¿Cuáles son los riesgos críticos y qué tratamiento recomiendas?');
    if (m.includes('incidentes')) lista.push('¿Qué incidentes siguen abiertos y cómo los priorizo?');
    if (m.includes('auditorias')) lista.push('¿Qué no conformidades siguen abiertas y qué acciones correctivas sugieres?');
    lista.push('¿Qué controles del Anexo A ayudan a proteger los respaldos de información?');
    lista.push('¿Cómo registro un incidente de seguridad en GSI UPTex?');
    return lista.slice(0, 5);
});

function fechaCorta(iso) {
    if (!iso) return '';
    const f = new Date(iso);
    const hoy = new Date();
    return f.toDateString() === hoy.toDateString()
        ? f.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
        : f.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' });
}

async function bajar() {
    await nextTick();
    if (refMensajes.value) refMensajes.value.scrollTop = refMensajes.value.scrollHeight;
}

function ajustarAltura() {
    const t = refEntrada.value;
    if (!t) return;
    t.style.height = 'auto';
    t.style.height = `${Math.min(t.scrollHeight, 180)}px`;
}

async function listar() {
    const { data } = await iaApi.conversaciones();
    conversaciones.value = data.data;
}

async function abrir(uuid) {
    error.value = '';
    historialAbierto.value = false;
    try {
        const { data } = await iaApi.conversacion(uuid);
        actual.value = { uuid: data.data.uuid, titulo: data.data.titulo };
        mensajes.value = data.data.mensajes;
        if (route.query.c !== uuid) router.replace({ query: { c: uuid } });
        bajar();
    } catch (e) {
        toast.error(e.message);
        nueva();
    }
}

function nueva() {
    actual.value = null;
    mensajes.value = [];
    error.value = '';
    historialAbierto.value = false;
    if (route.query.c) router.replace({ query: {} });
    nextTick(() => refEntrada.value?.focus());
}

async function enviar(texto) {
    const mensaje = (texto ?? borrador.value).trim();
    if (!mensaje || enviando.value) return;
    enviando.value = true;
    error.value = '';
    // Se muestra de inmediato; el backend devuelve la versión guardada (con secretos redactados).
    const temporal = { id: `tmp-${Date.now()}`, rol: 'usuario', contenido: mensaje, estado: 'ok', fuentes: [] };
    mensajes.value.push(temporal);
    borrador.value = '';
    nextTick(ajustarAltura);
    bajar();
    try {
        const { data } = await iaApi.enviar(mensaje, actual.value?.uuid);
        const r = data.data;
        mensajes.value.splice(mensajes.value.indexOf(temporal), 1, r.pregunta, r.respuesta);
        if (!actual.value) {
            actual.value = r.conversacion;
            router.replace({ query: { c: r.conversacion.uuid } });
        }
        listar();
    } catch (e) {
        mensajes.value.splice(mensajes.value.indexOf(temporal), 1);
        borrador.value = mensaje;
        error.value = e.message || 'SGSI Intelligence Assistant no está disponible en este momento.';
    } finally {
        enviando.value = false;
        bajar();
        nextTick(() => refEntrada.value?.focus());
    }
}

async function eliminar() {
    eliminando.value = true;
    try {
        await iaApi.eliminarConversacion(porEliminar.value.uuid);
        if (actual.value?.uuid === porEliminar.value.uuid) nueva();
        porEliminar.value = null;
        await listar();
        toast.exito('Conversación eliminada.');
    } catch (e) {
        toast.error(e.message);
    } finally {
        eliminando.value = false;
    }
}

watch(() => route.query.c, (uuid) => {
    if (uuid && uuid !== actual.value?.uuid) abrir(uuid);
});

onMounted(async () => {
    try {
        const { data } = await iaApi.estado();
        estado.value = data.data;
    } catch (e) {
        toast.error(e.message);
    }
    await listar();
    if (route.query.c) abrir(route.query.c);
});
</script>

<style scoped>
.asis { display: flex; flex-direction: column; gap: var(--space-4); }
.asis__cabecera { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); flex-wrap: wrap; }
.asis__pestanas {
    display: flex; gap: 4px; padding: 4px; border-radius: var(--radius-md); background: var(--bg-subtle);
    max-width: 100%; overflow-x: auto; scrollbar-width: none;
}
.asis__pestanas::-webkit-scrollbar { display: none; }
.asis__pestana {
    flex-shrink: 0; white-space: nowrap;
    display: inline-flex; align-items: center; gap: 6px; min-height: 36px; padding: 6px 12px; border: none; border-radius: var(--radius-sm);
    background: transparent; color: var(--text-muted); font: inherit; font-size: var(--text-sm); font-weight: 600; cursor: pointer;
}
.asis__pestana:hover { color: var(--text); }
.asis__pestana--activa { background: var(--surface); color: var(--c-primary); box-shadow: var(--shadow-sm); }
.asis__aviso {
    display: flex; gap: 8px; align-items: center; padding: 10px 14px; border-radius: var(--radius-md);
    background: var(--c-amber-soft); color: var(--c-amber); font-size: var(--text-sm);
}

/* ---- Conversación ---- */
.asis__chat {
    display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 0; min-height: 0;
    height: calc(100dvh - 230px); min-height: 520px;
    border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface); overflow: hidden;
}
.asis__historial {
    display: flex; flex-direction: column; gap: var(--space-2); padding: var(--space-4) var(--space-3);
    border-right: 1px solid var(--border); background: var(--bg-subtle); overflow-y: auto;
}
.asis__historial-titulo {
    display: flex; align-items: center; gap: 6px; margin: var(--space-3) 4px 0; font-size: 0.66rem; font-weight: 700;
    letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-subtle);
}
.asis__historial-vacio { margin: 4px; font-size: var(--text-xs); color: var(--text-muted); }
.asis__historial-lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px; }
.asis__historial-lista li { position: relative; display: flex; align-items: stretch; }
.asis__conv {
    flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 8px 34px 8px 10px;
    border: none; border-radius: var(--radius-sm); background: transparent; color: var(--text); font: inherit; text-align: left; cursor: pointer;
}
.asis__conv:hover { background: var(--surface-hover); }
.asis__conv--activa { background: var(--c-primary-soft); }
.asis__conv--activa .asis__conv-titulo { color: var(--c-primary); }
.asis__conv-titulo { width: 100%; font-size: var(--text-sm); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.asis__conv-fecha { font-size: 0.68rem; color: var(--text-subtle); }
.asis__conv-borrar {
    position: absolute; right: 4px; top: 50%; transform: translateY(-50%); display: grid; place-items: center; width: 28px; height: 28px;
    border: none; border-radius: 6px; background: transparent; color: var(--text-subtle); cursor: pointer; opacity: 0;
    transition: opacity var(--transition-fast), color var(--transition-fast);
}
.asis__historial-lista li:hover .asis__conv-borrar, .asis__conv-borrar:focus-visible { opacity: 1; }
.asis__conv-borrar:hover { color: var(--c-risk); background: var(--c-risk-soft); }

.asis__principal { display: flex; flex-direction: column; min-height: 0; min-width: 0; }
.asis__barra-movil { display: none; }
.asis__mensajes { flex: 1; overflow-y: auto; padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-4); scroll-behavior: smooth; }

.asis__bienvenida { margin: auto; max-width: 640px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: var(--space-3); }
.asis__bienvenida-icono {
    display: grid; place-items: center; width: 68px; height: 68px; border-radius: 20px; color: #fff;
    background: var(--brand-gradient); box-shadow: var(--shadow-brand);
}
.asis__bienvenida h2 { margin: 0; font-family: var(--font-display); font-size: var(--text-xl); color: var(--text); }
.asis__bienvenida p { margin: 0; font-size: var(--text-sm); color: var(--text-muted); line-height: 1.6; }
.asis__sugerencias { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-2); width: 100%; margin-top: var(--space-2); }
.asis__sugerencia {
    display: flex; align-items: flex-start; gap: 8px; padding: 12px 14px; border: 1px solid var(--border); border-radius: var(--radius-md);
    background: var(--surface); color: var(--text); font: inherit; font-size: var(--text-sm); text-align: left; cursor: pointer; line-height: 1.45;
    transition: border-color var(--transition-fast), background var(--transition-fast), transform var(--transition-fast);
}
.asis__sugerencia :deep(svg) { flex-shrink: 0; margin-top: 2px; color: var(--c-primary); }
.asis__sugerencia:hover { border-color: var(--c-primary); background: var(--c-primary-soft); transform: translateY(-1px); }

.asis__msg { display: flex; gap: 10px; align-items: flex-start; max-width: 860px; animation: asis-entrada 220ms var(--ease) backwards; }
.asis__msg--usuario { align-self: flex-end; justify-content: flex-end; }
.asis__msg--asistente { align-self: flex-start; width: 100%; }
@keyframes asis-entrada { from { opacity: 0; transform: translateY(6px); } }
.asis__avatar {
    flex-shrink: 0; display: grid; place-items: center; width: 30px; height: 30px; border-radius: 9px; color: #fff; background: var(--brand-gradient);
}
.asis__burbuja { min-width: 0; padding: 12px 16px; border-radius: 16px; display: flex; flex-direction: column; gap: 6px; }
.asis__msg--usuario .asis__burbuja { background: var(--c-primary); color: #fff; border-bottom-right-radius: 4px; max-width: 640px; }
.asis__msg--asistente .asis__burbuja { background: var(--bg-subtle); border: 1px solid var(--border); border-top-left-radius: 4px; flex: 1; }
.asis__msg--rechazado .asis__burbuja, .asis__msg--bloqueado.asis__msg--asistente .asis__burbuja { background: var(--c-amber-soft); border-color: var(--c-amber); }
.asis__texto-usuario { margin: 0; font-size: var(--text-sm); line-height: 1.55; white-space: pre-wrap; overflow-wrap: anywhere; }
.asis__etiqueta-estado { display: flex; align-items: center; gap: 4px; margin: 0; font-size: 0.7rem; font-weight: 700; color: var(--c-amber); text-transform: uppercase; letter-spacing: 0.04em; }
.asis__fuentes { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding-top: 8px; border-top: 1px dashed var(--border); }
.asis__fuentes-titulo { font-size: 0.7rem; font-weight: 700; color: var(--text-muted); }
.asis__fuente {
    display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px; font-size: 0.7rem;
    background: var(--surface); border: 1px solid var(--border); color: var(--text-muted);
}
.asis__fuente--documento { color: var(--c-primary); border-color: var(--c-primary-soft); background: var(--c-primary-soft); }
.asis__error {
    display: flex; gap: 8px; align-items: center; padding: 10px 12px; border-radius: var(--radius-sm); font-size: var(--text-sm);
    background: var(--c-risk-soft); color: var(--c-risk-strong); border: 1px solid var(--c-risk);
}

.asis__composer {
    display: flex; flex-direction: column; gap: 8px; margin: 0 var(--space-4); padding: 10px 12px;
    border: 1px solid var(--border-strong); border-radius: var(--radius-lg); background: var(--surface);
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}
.asis__composer:focus-within { border-color: var(--c-primary); box-shadow: var(--focus-ring); }
.asis__composer textarea {
    width: 100%; min-height: 24px; max-height: 180px; resize: none; border: none; outline: none; background: transparent;
    color: var(--text); font: inherit; font-size: var(--text-base); line-height: 1.5;
}
.asis__composer-pie { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.asis__contador { font-size: var(--text-xs); color: var(--text-subtle); font-variant-numeric: tabular-nums; }
.asis__contador--limite { color: var(--c-amber); }
.asis__privacidad { display: flex; gap: 6px; align-items: center; justify-content: center; margin: 8px var(--space-4) 12px; font-size: 0.7rem; color: var(--text-subtle); text-align: center; }
.asis__privacidad :deep(svg) { flex-shrink: 0; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }

@media (max-width: 900px) {
    .asis__chat { grid-template-columns: 1fr; height: calc(100dvh - 200px); }
    .asis__historial { display: none; }
    .asis__chat--historial .asis__historial { display: flex; position: absolute; z-index: 5; inset: 52px 0 0; border-right: none; }
    .asis__chat { position: relative; }
    .asis__barra-movil { display: flex; justify-content: space-between; gap: 8px; padding: 10px var(--space-4); border-bottom: 1px solid var(--border); }
    .asis__mensajes { padding: var(--space-4); }
}
@media (max-width: 640px) {
    .asis__chat { height: calc(100dvh - 250px); min-height: 440px; border-radius: var(--radius-md); }
    .asis__composer { margin: 0 var(--space-2); }
    .asis__msg--usuario .asis__burbuja { max-width: 88%; }
    .asis__avatar { display: none; }
    .asis__pestanas { width: 100%; }
}
@media (prefers-reduced-motion: reduce) { .asis__msg { animation: none; } .asis__mensajes { scroll-behavior: auto; } }
</style>
