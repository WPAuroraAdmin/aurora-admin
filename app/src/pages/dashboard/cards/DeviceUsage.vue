<script setup>
import { computed } from 'vue';
import AuroraCard from '../components/AuroraCard.vue';
import BarList from '../components/BarList.vue';

const props = defineProps({ items: { type: Array, default: () => [] } });

const cap = (s) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : s);
const list = computed(() =>
  props.items.map((d) => ({ label: `${cap(d.device)} · ${d.pct}%`, count: d.count }))
);
</script>

<template>
  <AuroraCard title="Device Usage" subtitle="How visitors browse">
    <BarList :items="list" empty-text="No visits in this range yet." />
  </AuroraCard>
</template>
