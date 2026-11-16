<template>
  <label class="app-field" :class="{ 'app-field--error': error }">
    <span v-if="label" class="app-field__label">{{ label }}<span v-if="requerido" class="app-field__req"> *</span></span>
    <span class="app-field__envoltura" :class="{ 'app-field__envoltura--icono': icono, 'app-field__envoltura--toggle': esPassword }">
      <AppIcon v-if="icono" :name="icono" :size="18" class="app-field__icono" />
      <input
        class="app-field__control"
        :type="tipoEfectivo"
        :value="modelValue"
        :placeholder="placeholder"
        :required="requerido"
        :aria-invalid="error ? 'true' : undefined"
        v-bind="$attrs"
        @input="$emit('update:modelValue', $event.target.value)"
      />
      <button
        v-if="esPassword"
        type="button"
        class="app-field__toggle"
        :aria-label="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'"
        :title="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'"
        @click.prevent="visible = !visible"
      >
        <AppIcon :name="visible ? 'ojo-off' : 'ojo'" :size="18" />
      </button>
    </span>
    <Transition name="aviso" mode="out-in">
      <span v-if="error" :key="error" class="app-field__error" role="alert"><AppIcon name="alerta" :size="14" />{{ error }}</span>
      <span v-else-if="ayuda" class="app-field__ayuda">{{ ayuda }}</span>
    </Transition>
  </label>
</template>

<script setup>
import { ref, computed } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    requerido: { type: Boolean, default: false },
    error: { type: String, default: '' },
    ayuda: { type: String, default: '' },
    icono: { type: String, default: '' }, // opcional: icono a la izquierda
});
defineEmits(['update:modelValue']);

// Solo presentación: permite ver/ocultar el texto de los campos de contraseña.
const visible = ref(false);
const esPassword = computed(() => props.type === 'password');
const tipoEfectivo = computed(() => (esPassword.value && visible.value ? 'text' : props.type));
</script>

<style scoped>
.app-field { display: flex; flex-direction: column; gap: 6px; font-size: var(--text-sm); min-width: 0; }
.app-field__label { font-weight: 600; color: var(--text); font-size: var(--text-sm); }
.app-field__req { color: var(--c-risk); }

.app-field__envoltura { position: relative; display: flex; align-items: center; }
.app-field__icono {
    position: absolute; left: 13px; color: var(--text-subtle); pointer-events: none;
    transition: color var(--transition-fast);
}
.app-field__control {
    width: 100%; min-height: 44px;
    font-family: var(--font-body); font-size: var(--text-base); padding: 10px 14px;
    border: 1px solid var(--border-strong); border-radius: var(--radius-md);
    background: var(--surface); color: var(--text);
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast), background var(--transition-fast);
}
.app-field__envoltura--icono .app-field__control { padding-left: 42px; }
.app-field__envoltura--toggle .app-field__control { padding-right: 46px; }
.app-field__control:hover { border-color: var(--c-gray-400); }
.app-field__control:focus { border-color: var(--c-primary); outline: none; box-shadow: var(--focus-ring); }
.app-field__envoltura:focus-within .app-field__icono { color: var(--c-primary); }
.app-field__control::-webkit-calendar-picker-indicator { cursor: pointer; opacity: 0.7; }
[data-theme='dark'] .app-field__control::-webkit-calendar-picker-indicator { filter: invert(0.85); }

.app-field__toggle {
    position: absolute; right: 4px; display: grid; place-items: center; width: 38px; height: 38px;
    border: none; background: none; border-radius: var(--radius-sm); color: var(--text-subtle); cursor: pointer;
    transition: color var(--transition-fast), background var(--transition-fast), transform var(--transition-fast);
}
.app-field__toggle:hover { color: var(--c-primary); background: var(--c-primary-soft); }
.app-field__toggle:active { transform: scale(0.92); }
.app-field__toggle :deep(.app-icon) { transition: transform var(--transition-base); }
.app-field__toggle:hover :deep(.app-icon) { transform: scale(1.08); }

.app-field--error .app-field__control { border-color: var(--c-risk); animation: sacudir 320ms var(--ease) 1; }
.app-field--error .app-field__control:focus { box-shadow: 0 0 0 3px var(--c-risk-soft); }
.app-field__error { display: inline-flex; align-items: center; gap: 5px; color: var(--c-risk); font-size: var(--text-xs); font-weight: 500; }
.app-field__ayuda { color: var(--text-muted); font-size: var(--text-xs); }
</style>
