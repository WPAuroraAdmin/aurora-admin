<script setup>
import AuroraCard from '../components/AuroraCard.vue';
import StatRow from '../components/StatRow.vue';
import AreaChart from '../components/AreaChart.vue';

defineProps({ data: { type: Object, default: () => ({}) } });
</script>

<template>
  <AuroraCard title="User Analytics" subtitle="User registration trends">
    <StatRow
      :boxed="false"
      :stats="[
        { value: data.total || 0, label: 'Total Users' },
        { value: data.inRange || 0, label: 'In Range' },
        { value: data.recent7d || 0, label: 'Recent (7d)' },
      ]"
    />
    <div class="ua__chart">
      <AreaChart :series="data.series || []" :height="150" />
    </div>
  </AuroraCard>
</template>

<style scoped>
.ua__chart { margin-top: 20px; }
</style>
