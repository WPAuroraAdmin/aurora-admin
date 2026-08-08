<script setup>
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  label: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <label class="toggle" :class="{ 'toggle--disabled': disabled }">
    <span v-if="label" class="toggle__label">{{ label }}</span>
    <button
      type="button"
      class="toggle__switch"
      :class="{ 'toggle__switch--on': modelValue }"
      role="switch"
      :aria-checked="modelValue"
      :disabled="disabled"
      @click="!disabled && $emit('update:modelValue', !modelValue)"
    >
      <span class="toggle__thumb" />
    </button>
  </label>
</template>

<style scoped>
.toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  cursor: pointer;
}

.toggle__label {
  font-size: 0.875rem;
}

.toggle__switch {
  position: relative;
  width: 2.5rem;
  height: 1.4rem;
  border-radius: 999px;
  border: none;
  background: var(--aurora-border);
  cursor: pointer;
  padding: 0;
  transition: background-color 0.15s ease;
  flex-shrink: 0;
}

.toggle__switch--on {
  background: var(--aurora-accent);
}

.toggle--disabled { cursor: default; opacity: 0.55; }
.toggle--disabled .toggle__switch { cursor: default; }

.toggle__thumb {
  position: absolute;
  top: 0.15rem;
  left: 0.15rem;
  width: 1.1rem;
  height: 1.1rem;
  border-radius: 50%;
  background: #ffffff;
  transition: transform 0.15s ease;
}

.toggle__switch--on .toggle__thumb {
  transform: translateX(1.1rem);
}
</style>
