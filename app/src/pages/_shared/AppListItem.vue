<script setup>
defineProps({
  icon: { type: String, default: '' },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  active: { type: Boolean, default: false },
});
defineEmits(['click']);
</script>

<template>
  <button
    type="button"
    class="app-list-item"
    :class="{ 'app-list-item--active': active }"
    @click="$emit('click')"
  >
    <span v-if="icon" class="app-list-item__icon">
      <span class="dashicons" :class="icon" />
    </span>
    <span class="app-list-item__text">
      <span class="app-list-item__title">{{ title }}</span>
      <span v-if="subtitle" class="app-list-item__subtitle">{{ subtitle }}</span>
    </span>
    <slot name="trailing" />
  </button>
</template>

<style scoped>
.app-list-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px;
  border: none;
  background: none;
  cursor: pointer;
  border-radius: var(--aurora-radius-md);
  text-align: left;
}
.app-list-item:hover {
  background: var(--aurora-bg-subtle);
}
.app-list-item--active {
  background: var(--aurora-accent-soft);
}
.app-list-item__icon {
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  border-radius: var(--aurora-radius-md);
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
}
.app-list-item--active .app-list-item__icon {
  color: var(--aurora-accent);
}
.app-list-item__text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}
.app-list-item__title {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--aurora-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.app-list-item--active .app-list-item__title {
  color: var(--aurora-accent);
}
.app-list-item__subtitle {
  font-size: 0.75rem;
  color: var(--aurora-text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
