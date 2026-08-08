<script setup>
import { computed } from 'vue';

const props = defineProps({
  // 0-100. When null, renders an indeterminate/pulsing bar instead of a
  // determinate fill — used for phases with no cheap way to compute a
  // percentage (e.g. "building the file list" before zipping starts).
  percent: { type: Number, default: null },
  label: { type: String, default: '' },
});

const clamped = computed(() => (props.percent === null ? null : Math.max(0, Math.min(100, props.percent))));
</script>

<template>
  <div class="progress-bar">
    <div v-if="label" class="progress-bar__label">{{ label }}</div>
    <div class="progress-bar__track" :class="{ 'progress-bar__track--indeterminate': clamped === null }">
      <div
        v-if="clamped !== null"
        class="progress-bar__fill"
        :style="{ width: clamped + '%' }"
      />
    </div>
  </div>
</template>

<style scoped>
.progress-bar__label {
  font-size: 0.8125rem;
  color: var(--aurora-text-muted);
  margin-bottom: 6px;
}
.progress-bar__track {
  height: 8px;
  border-radius: 999px;
  background: var(--aurora-bg-subtle);
  overflow: hidden;
  position: relative;
}
.progress-bar__fill {
  height: 100%;
  border-radius: 999px;
  background: var(--aurora-accent);
  transition: width 0.3s ease;
}
.progress-bar__track--indeterminate::before {
  content: '';
  position: absolute;
  top: 0; bottom: 0;
  width: 40%;
  border-radius: 999px;
  background: var(--aurora-accent);
  animation: progress-bar-indeterminate 1.2s ease-in-out infinite;
}
@keyframes progress-bar-indeterminate {
  0% { left: -40%; }
  100% { left: 100%; }
}
</style>
