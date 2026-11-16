<template>
  <div class="ia-decision">
    <template v-if="!decidida">
      <p class="ia-decision__nota">
        <AppIcon name="info" :size="14" />
        Es una propuesta: revísala antes de usarla. {{ notaAceptar }}
      </p>
      <div class="ia-decision__botones">
        <AppButton tamano="sm" variante="secundaria" icono="cerrar" :cargando="enviando === 'rechazada'" @click="decidir('rechazada')">
          Rechazar
        </AppButton>
        <AppButton tamano="sm" icono="check" :cargando="enviando === 'aceptada'" @click="decidir('aceptada')">
          {{ textoAceptar }}
        </AppButton>
      </div>
    </template>
    <p v-else class="ia-decision__estado" :class="`ia-decision__estado--${decidida}`">
      <AppIcon :name="decidida === 'rechazada' ? 'cerrar' : 'check'" :size="15" />
      {{ decidida === 'rechazada' ? 'Propuesta rechazada.' : decidida === 'parcial' ? 'Propuesta aplicada parcialmente.' : 'Propuesta aceptada.' }}
      Quedó registrado en la bitácora.
    </p>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import iaApi from '../../api/ia';
import { useToastStore } from '../../stores/toast';
import AppButton from '../ui/AppButton.vue';
import AppIcon from '../ui/AppIcon.vue';

const props = defineProps({
    analisisUuid: { type: String, required: true },
    textoAceptar: { type: String, default: 'Aceptar propuesta' },
    notaAceptar: { type: String, default: '' },
    // Acción real al aceptar (p. ej. aplicar valores al formulario). Puede devolver
    // { decision: 'parcial', elementos: [...] }. Si lanza error, no se registra la decisión.
    alAceptar: { type: Function, default: null },
});
const emit = defineEmits(['decidido']);
const toast = useToastStore();

const enviando = ref('');
const decidida = ref('');
watch(() => props.analisisUuid, () => { decidida.value = ''; });

async function decidir(decision) {
    enviando.value = decision;
    try {
        let final = decision;
        let elementos = [];
        if (decision === 'aceptada' && props.alAceptar) {
            const r = (await props.alAceptar()) || {};
            final = r.decision || 'aceptada';
            elementos = r.elementos || [];
        }
        await iaApi.decidir(props.analisisUuid, final, elementos);
        decidida.value = final;
        emit('decidido', final);
    } catch (e) {
        toast.error(e.message || 'No se pudo registrar la decisión.');
    } finally {
        enviando.value = '';
    }
}
</script>

<style scoped>
.ia-decision { display: flex; flex-direction: column; gap: var(--space-2); padding-top: var(--space-3); border-top: 1px dashed var(--border); }
.ia-decision__nota { display: flex; gap: 6px; align-items: flex-start; margin: 0; font-size: var(--text-xs); color: var(--text-muted); line-height: 1.5; }
.ia-decision__botones { display: flex; gap: var(--space-2); justify-content: flex-end; flex-wrap: wrap; }
.ia-decision__estado { display: flex; gap: 6px; align-items: center; margin: 0; font-size: var(--text-sm); font-weight: 600; color: var(--c-success); }
.ia-decision__estado--rechazada { color: var(--text-muted); }
</style>
