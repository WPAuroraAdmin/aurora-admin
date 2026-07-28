<script setup>
import { computed } from 'vue';

// items: [{ label, count }]
const props = defineProps({
  items: { type: Array, default: () => [] },
  emptyText: { type: String, default: 'No data yet.' },
});

const max = computed(() => Math.max(1, ...props.items.map((i) => i.count)));
</script>

<template>
  <div class="barlist">
    <div v-for="(it, i) in items" :key="i" class="barlist__row">
      <div class="barlist__head">
        <span class="barlist__label" :title="it.label">{{ it.label }}</span>
        <span class="barlist__count">{{ it.count }}</span>
      </div>
      <div class="barlist__track">
        <div class="barlist__fill" :style="{ width: (it.count / max) * 100 + '%' }" />
      </div>
    </div>
    <p v-if="!items.length" class="barlist__empty">{{ emptyText }}</p>
  </div>
</template>

<style scoped>
.barlist__row { margin-bottom: 12px; }
.barlist__head {
  display: flex; justify-content: space-between; gap: 12px; margin-bottom: 5px;
}
.barlist__label {
  font-size: 0.8125rem; color: var(--aurora-text);
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.barlist__count { font-size: 0.8125rem; color: var(--aurora-text-muted); flex-shrink: 0; }
.barlist__track {
  height: 6px; border-radius: 999px;
  background: rgba(127,127,127,0.18); overflow: hidden;
}
.barlist__fill { height: 100%; border-radius: 999px; background: var(--aurora-accent); }
.barlist__empty { font-size: 0.8125rem; color: var(--aurora-text-muted); padding: 8px 0; }
</style>
