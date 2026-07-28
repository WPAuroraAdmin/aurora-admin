<script setup>
// stats: [{ value, label }]
defineProps({
  stats: { type: Array, required: true },
  boxed: { type: Boolean, default: true },
});
</script>

<template>
  <div class="statrow" :class="{ 'statrow--boxed': boxed }">
    <div v-for="(s, i) in stats" :key="i" class="statrow__item">
      <div class="statrow__value">{{ s.value }}</div>
      <div class="statrow__label">{{ s.label }}</div>
    </div>
  </div>
</template>

<style scoped>
.statrow {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: 1fr;
  text-align: center;
}
.statrow--boxed {
  background: var(--aurora-bg-subtle);
  border-radius: var(--aurora-radius-md);
  padding: 16px 8px;
}
.statrow__value {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--aurora-text);
  line-height: 1.1;
}
.statrow__label {
  margin-top: 4px;
  font-size: 0.75rem;
  color: var(--aurora-text-muted);
}
</style>
