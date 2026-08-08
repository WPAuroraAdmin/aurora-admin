<script setup>
import AuroraCard from '../components/AuroraCard.vue';
import StatRow from '../components/StatRow.vue';

defineProps({ bounce: { type: Object, default: () => ({}) } });
</script>

<template>
  <AuroraCard title="Engagement" subtitle="Bounce rate & time on site">
    <StatRow
      :stats="[
        { value: (bounce.rate || 0) + '%', label: 'Bounce Rate' },
        { value: bounce.avgTime || '0s', label: 'Avg. Time' },
        { value: bounce.sessions || 0, label: 'Sessions' },
      ]"
    />
  </AuroraCard>
</template>
