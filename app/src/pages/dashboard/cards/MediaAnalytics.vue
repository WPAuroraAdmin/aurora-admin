<script setup>
import AuroraCard from '../components/AuroraCard.vue';

defineProps({ data: { type: Object, default: () => ({}) } });
</script>

<template>
  <AuroraCard title="Media Analytics" subtitle="Media library overview">
    <div class="ma__top">
      <div class="ma__big">
        <div class="ma__big-num">{{ data.totalFiles || 0 }}</div>
        <div class="ma__big-label">Total Files</div>
      </div>
      <div class="ma__big">
        <div class="ma__big-num">{{ data.totalSize || '0 B' }}</div>
        <div class="ma__big-label">Total Size</div>
      </div>
    </div>

    <div class="ma__grid">
      <div class="ma__box">
        <div class="ma__box-num">{{ data.recent30d || 0 }}</div>
        <div class="ma__box-label">Recent (30d)</div>
      </div>
      <div class="ma__box">
        <div class="ma__box-num">{{ data.unused || 0 }}</div>
        <div class="ma__box-label">Unused</div>
      </div>
      <div class="ma__box">
        <div class="ma__box-num">{{ data.largeFiles || 0 }}</div>
        <div class="ma__box-label">Large Files</div>
      </div>
      <div class="ma__box">
        <div class="ma__box-num">{{ data.diskUsage || '0 B' }}</div>
        <div class="ma__box-label">Disk Usage</div>
      </div>
    </div>

    <template #footer>
      <a :href="data.manageUrl" class="viewall">Manage Media ›</a>
    </template>
  </AuroraCard>
</template>

<style scoped>
.ma__top {
  display: grid; grid-template-columns: 1fr 1fr; text-align: center;
  margin-bottom: 18px;
}
.ma__big-num { font-size: 1.75rem; font-weight: 700; color: var(--aurora-text); }
.ma__big-label { margin-top: 4px; font-size: 0.75rem; color: var(--aurora-text-muted); }
.ma__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.ma__box {
  background: var(--aurora-bg-subtle);
  border-radius: var(--aurora-radius-md);
  padding: 14px;
  text-align: center;
}
.ma__box-num { font-size: 1.25rem; font-weight: 700; color: var(--aurora-text); }
.ma__box-label { margin-top: 2px; font-size: 0.6875rem; color: var(--aurora-text-muted); }
</style>
