<script setup>
import AuroraCard from '../components/AuroraCard.vue';

defineProps({ count: { type: Number, default: 0 } });
</script>

<template>
  <AuroraCard title="Active Users" subtitle="Visitors in the last 5 minutes">
    <div class="au">
      <span class="au__pulse" />
      <div class="au__num">{{ count }}</div>
      <div class="au__label">active now</div>
    </div>
  </AuroraCard>
</template>

<style scoped>
.au {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  text-align: center; padding: 24px 0; height: 100%;
}
.au__pulse {
  width: 10px; height: 10px; border-radius: 50%;
  background: #34d399; margin-bottom: 14px;
  box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.6);
  animation: au-pulse 1.8s infinite;
}
@keyframes au-pulse {
  0% { box-shadow: 0 0 0 0 rgba(52,211,153,0.5); }
  70% { box-shadow: 0 0 0 12px rgba(52,211,153,0); }
  100% { box-shadow: 0 0 0 0 rgba(52,211,153,0); }
}
.au__num { font-size: 2.5rem; font-weight: 700; color: var(--aurora-text); line-height: 1; }
.au__label { margin-top: 6px; font-size: 0.8125rem; color: var(--aurora-text-muted); }
</style>
