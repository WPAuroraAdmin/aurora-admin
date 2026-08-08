<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
});
</script>

<template>
  <section class="acard">
    <header class="acard__head">
      <div class="acard__titles">
        <h2 class="acard__title">{{ title }}</h2>
        <p v-if="subtitle" class="acard__subtitle">{{ subtitle }}</p>
      </div>
      <div class="acard__head-right">
        <slot name="head-right" />
      </div>
    </header>
    <div class="acard__body">
      <slot />
    </div>
    <footer v-if="$slots.footer" class="acard__foot">
      <slot name="footer" />
    </footer>
  </section>
</template>

<style scoped>
.acard {
  display: flex;
  flex-direction: column;
  height: 100%;
  /* Deeper than the generic --aurora-card-bg — the Dashboard is now
     wrapped in its own frame (dashboardFrame.js's .aurora-dashboard-frame,
     using --aurora-frame-bg), and that's close in tone to --aurora-card-bg
     for most presets, so cards would nearly disappear into it otherwise. */
  background: var(--aurora-card-bg-deep, var(--aurora-card-bg));
  border: 1px solid var(--aurora-card-border);
  border-radius: var(--aurora-radius-lg);
  padding: 20px;
  box-sizing: border-box;
}
.acard__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 18px;
}
.acard__title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 600;
  color: var(--aurora-text);
}
.acard__subtitle {
  margin: 2px 0 0;
  font-size: 0.8125rem;
  color: var(--aurora-text-muted);
}
.acard__head-right {
  flex-shrink: 0;
  font-size: 0.75rem;
  color: var(--aurora-text-muted);
}
.acard__body {
  flex: 1;
}
.acard__foot {
  margin-top: 16px;
  padding-top: 12px;
  display: flex;
  justify-content: flex-end;
}
</style>
