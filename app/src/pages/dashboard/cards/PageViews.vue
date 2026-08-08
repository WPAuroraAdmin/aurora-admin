<script setup>
import AuroraCard from '../components/AuroraCard.vue';
import StatRow from '../components/StatRow.vue';
import AreaChart from '../components/AreaChart.vue';

defineProps({ data: { type: Object, default: () => ({}) } });
</script>

<template>
  <AuroraCard title="Page Views" subtitle="Traffic over the selected range">
    <StatRow
      :boxed="false"
      :stats="[
        { value: data.total || 0, label: 'Total Views' },
        { value: data.unique || 0, label: 'Unique Visitors' },
      ]"
    />
    <div class="pv__chart">
      <AreaChart :series="data.series || []" :height="150" />
    </div>
  </AuroraCard>
</template>

<style scoped>
.pv__chart { margin-top: 20px; }
</style>
