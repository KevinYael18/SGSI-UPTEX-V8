<template>
  <label class="app-field">
    <span v-if="label" class="app-field__label">{{ label }}<span v-if="requerido" class="app-field__req"> *</span></span>
    <span class="app-field__envoltura">
      <select
        class="app-field__control"
        :value="modelValue"
        :required="requerido"
        :disabled="disabled"
        @change="$emit('update:modelValue', $event.target.value)"
      >
        <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
        <option v-for="op in opciones" :key="op.value" :value="op.value">{{ op.label }}</option>
      </select>
      <AppIcon name="chevron-abajo" :size="16" class="app-field__chevron" />
    </span>
  </label>
</template>

<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    opciones: { type: Array, default: () => [] }, // [{ value, label }]
    requerido: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});
defineEmits(['update:modelValue']);
</script>

<style scoped>
.app-field { display: flex; flex-direction: column; gap: 6px; font-size: var(--text-sm); min-width: 0; }
.app-field__label { font-weight: 600; color: var(--text); }
.app-field__req { color: var(--c-risk); }
.app-field__envoltura { position: relative; display: flex; align-items: center; min-width: 0; }
.app-field__control {
    width: 100%; min-width: 0; min-height: 44px;
    appearance: none; -webkit-appearance: none;
    font-family: var(--font-body); font-size: var(--text-base); padding: 10px 40px 10px 14px;
    border: 1px solid var(--border-strong); border-radius: var(--radius-md);
    background: var(--surface); color: var(--text); cursor: pointer;
    text-overflow: ellipsis;
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}
.app-field__control:hover:not(:disabled) { border-color: var(--c-gray-400); }
.app-field__control:focus { border-color: var(--c-primary); outline: none; box-shadow: var(--focus-ring); }
.app-field__control:disabled { opacity: 0.6; cursor: not-allowed; background: var(--bg-subtle); }
.app-field__control option { background: var(--surface); color: var(--text); }
.app-field__chevron {
    position: absolute; right: 13px; color: var(--text-subtle); pointer-events: none;
    transition: color var(--transition-fast), transform var(--transition-fast);
}
.app-field__envoltura:focus-within .app-field__chevron { color: var(--c-primary); }
</style>
