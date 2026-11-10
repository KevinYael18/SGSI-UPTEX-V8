<template>
  <div>
    <h1>Configuración general</h1>
    <p class="subtitulo">Solo Administrador.</p>

    <form class="form config" @submit.prevent="guardar">
      <div class="config__cabecera">
        <span class="config__icono"><AppIcon name="configuracion" :size="20" /></span>
        <div>
          <strong>Parámetros del sistema</strong>
          <span>Los cambios aplican a toda la plataforma.</span>
        </div>
      </div>
      <div class="config__campos">
        <AppInput v-for="c in configuraciones" :key="c.clave" v-model="valores[c.clave]" :label="c.clave" :ayuda="c.descripcion" />
      </div>
      <div class="config__pie">
        <AppButton type="submit" icono="check" :cargando="guardando">Guardar cambios</AppButton>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import configuracionApi from '../../api/configuracion';
import { useToastStore } from '../../stores/toast';
import AppInput from '../../components/ui/AppInput.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import AppButton from '../../components/ui/AppButton.vue';

const configuraciones = ref([]);
const valores = reactive({});
const guardando = ref(false);
const toast = useToastStore();

async function cargar() {
    const { data } = await configuracionApi.listar();
    configuraciones.value = data.data;
    data.data.forEach((c) => { valores[c.clave] = c.valor; });
}

async function guardar() {
    guardando.value = true;
    try {
        await configuracionApi.actualizar({ ...valores });
        toast.exito('Configuración actualizada.');
    } catch (e) {
        toast.error(e.message);
    } finally {
        guardando.value = false;
    }
}

onMounted(cargar);
</script>

<style scoped>
.config {
    max-width: 760px; gap: 0; background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow: hidden;
}
.config__cabecera {
    display: flex; align-items: center; gap: var(--space-3); padding: var(--space-4) var(--space-5);
    border-bottom: 1px solid var(--border); background: var(--bg-subtle);
}
.config__cabecera div { display: flex; flex-direction: column; font-size: var(--text-xs); color: var(--text-muted); }
.config__cabecera strong { font-size: var(--text-base); color: var(--text); }
.config__icono { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 12px; background: var(--c-primary-soft); color: var(--c-primary); }
.config__campos {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); gap: var(--space-5);
    padding: var(--space-5);
}
.config__campos :deep(.app-field__label) { font-family: var(--font-mono); font-size: var(--text-xs); }
.config__pie { display: flex; justify-content: flex-end; padding: var(--space-4) var(--space-5); border-top: 1px solid var(--border); background: var(--bg-subtle); }
@media (max-width: 640px) {
    .config__campos { padding: var(--space-4); gap: var(--space-4); }
    .config__pie { padding: var(--space-3) var(--space-4); }
    .config__pie :deep(.app-btn) { width: 100%; }
}
</style>
