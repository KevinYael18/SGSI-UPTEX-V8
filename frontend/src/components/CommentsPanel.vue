<template>
  <div class="comments">
    <h3 class="comments__titulo"><AppIcon name="comentario" :size="18" />Comentarios internos</h3>
    <form class="comments__form" @submit.prevent="enviar">
      <textarea v-model="texto" rows="2" placeholder="Agregar un comentario…" required maxlength="2000" />
      <AppButton type="submit" tamano="sm" :cargando="enviando">Comentar</AppButton>
    </form>

    <ul class="comments__lista">
      <li v-if="cargando" class="comments__estado">Cargando comentarios…</li>
      <li v-else-if="items.length === 0" class="comments__estado">Sin comentarios todavía.</li>
      <li v-for="c in items" :key="c.id" class="comments__item">
        <UserAvatar class="comments__avatar" variante="suave" :nombre="c.autor || '?'" :foto="c.autor_foto_url" :size="32" aria-hidden="true" />
        <div class="comments__contenido">
          <div class="comments__meta">
            <strong>{{ c.autor }}</strong>
            <time>{{ new Date(c.creado_en).toLocaleString('es-MX') }}</time>
          </div>
          <p>{{ c.contenido }}</p>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import comentariosApi from '../api/comentarios';
import { useToastStore } from '../stores/toast';
import AppButton from './ui/AppButton.vue';
import UserAvatar from './ui/UserAvatar.vue';
import AppIcon from './ui/AppIcon.vue';

const props = defineProps({
    tipo: { type: String, required: true }, // incidentes | riesgos | auditorias | acciones-correctivas
    id: { type: [String, Number], required: true },
});

const items = ref([]);
const texto = ref('');
const cargando = ref(false);
const enviando = ref(false);
const toast = useToastStore();

async function cargar() {
    cargando.value = true;
    try {
        const { data } = await comentariosApi.listar(props.tipo, props.id);
        items.value = data.data;
    } catch (e) {
        toast.error(e.message);
    } finally {
        cargando.value = false;
    }
}

async function enviar() {
    if (!texto.value.trim()) return;
    enviando.value = true;
    try {
        await comentariosApi.crear(props.tipo, props.id, texto.value.trim());
        texto.value = '';
        await cargar();
    } catch (e) {
        toast.error(e.message);
    } finally {
        enviando.value = false;
    }
}

onMounted(cargar);
</script>

<style scoped>
.comments__titulo { display: flex; align-items: center; gap: 8px; margin-bottom: var(--space-4); font-size: 1rem; }
.comments__titulo :deep(svg) { color: var(--c-primary); }
.comments__form { display: flex; flex-direction: column; align-items: flex-end; gap: var(--space-2); margin-bottom: var(--space-4); }
.comments__form textarea { min-height: 72px; font-size: var(--text-sm); }
.comments__lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--space-3); }
.comments__item { display: flex; gap: var(--space-3); align-items: flex-start; }
.comments__avatar { font-size: var(--text-xs); }
.comments__contenido {
    flex: 1; min-width: 0; padding: var(--space-3); background: var(--bg-subtle); border: 1px solid var(--border);
    border-radius: 4px var(--radius-md) var(--radius-md) var(--radius-md);
}
.comments__meta { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 4px 8px; font-size: var(--text-xs); color: var(--text-muted); margin-bottom: 4px; }
.comments__meta strong { color: var(--text); }
.comments__contenido p { margin: 0; font-size: var(--text-sm); overflow-wrap: anywhere; }
.comments__estado { color: var(--text-muted); font-size: var(--text-sm); }
</style>
