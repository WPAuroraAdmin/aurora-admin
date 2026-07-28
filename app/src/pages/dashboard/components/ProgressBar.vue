<script setup>
defineProps({
  label: { type: String, required: true },
  pct: { type: Number, default: 0 },
  detail: { type: String, default: '' },
  gradient: { type: Boolean, default: false },
  icon: { type: String, default: '' },
});
</script>

<template>
  <div class="pbar">
    <div class="pbar__top">
      <span class="pbar__label">
        <span v-if="icon" class="pbar__icon">{{ icon }}</span>{{ label }}
      </span>
      <span class="pbar__pct">{{ pct }}%</span>
    </div>
    <div class="pbar__track">
      <div
        class="pbar__fill"
        :class="{ 'pbar__fill--gradient': gradient }"
        :style="{ width: Math.min(100, pct) + '%' }"
      />
    </div>
    <div v-if="detail" class="pbar__detail">{{ detail }}</div>
  </div>
</template>

<style scoped>
.pbar {
  background: var(--aurora-bg-subtle);
  border-radius: var(--aurora-radius-md);
  padding: 12px 14px;
}
.pbar__top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}
.pbar__label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--aurora-text);
}
.pbar__icon { color: var(--aurora-text-muted); }
.pbar__pct {
  font-size: 0.75rem;
  font-weight: 600;
  color: #34d399;
}
.pbar__track {
  height: 6px;
  border-radius: 999px;
  background: rgba(127, 127, 127, 0.18);
  overflow: hidden;
}
.pbar__fill {
  height: 100%;
  border-radius: 999px;
  background: var(--aurora-accent);
}
.pbar__fill--gradient {
  background: linear-gradient(90deg, #4f7bf5 0%, #a855f7 55%, #ef4444 100%);
}
.pbar__detail {
  margin-top: 6px;
  font-size: 0.6875rem;
  color: var(--aurora-text-muted);
}
</style>
