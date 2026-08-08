<script setup>
import AuroraCard from '../components/AuroraCard.vue';
import StatRow from '../components/StatRow.vue';
import ProgressBar from '../components/ProgressBar.vue';

defineProps({ data: { type: Object, default: () => ({}) } });
</script>

<template>
  <AuroraCard title="Server Health" subtitle="System performance and updates">
    <template #head-right>
      <span class="dot dot--green" /> {{ data.updates || 0 }} updates
    </template>

    <div class="sh__versions">
      <div class="sh__ver">
        <div class="sh__ver-label">‹› WordPress</div>
        <div class="sh__ver-num">{{ data.wp || '—' }}</div>
      </div>
      <div class="sh__ver">
        <div class="sh__ver-label">⚙ PHP</div>
        <div class="sh__ver-num">{{ data.php || '—' }}</div>
      </div>
    </div>

    <div class="sh__bars">
      <ProgressBar
        label="Memory Usage"
        icon="◍"
        :pct="data.memory ? data.memory.pct : 0"
        :detail="data.memory ? `${data.memory.used} / ${data.memory.total}` : ''"
      />
      <ProgressBar
        label="Disk Space"
        icon="▤"
        gradient
        :pct="data.disk ? data.disk.pct : 0"
        :detail="data.disk ? `${data.disk.used} / ${data.disk.total}` : ''"
      />
    </div>

    <StatRow
      :stats="[
        { value: data.plugins || 0, label: 'Plugins' },
        { value: data.themes || 0, label: 'Themes' },
        { value: data.core || 0, label: 'Core' },
      ]"
    />

    <div class="sh__foot">
      <span>{{ data.ssl ? '🔒 SSL enabled' : '⚠ SSL disabled' }}</span>
      <span>🕓 Timezone: {{ data.timezone || 'UTC' }}</span>
    </div>
  </AuroraCard>
</template>

<style scoped>
.sh__versions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }
.sh__ver {
  background: var(--aurora-bg-subtle);
  border-radius: var(--aurora-radius-md);
  padding: 12px 14px;
}
.sh__ver-label { font-size: 0.6875rem; color: var(--aurora-text-muted); }
.sh__ver-num { font-size: 1rem; font-weight: 600; color: var(--aurora-text); margin-top: 2px; }
.sh__bars { display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px; }
.sh__foot {
  display: flex; justify-content: space-between;
  margin-top: 16px; font-size: 0.6875rem; color: var(--aurora-text-muted);
}
</style>
