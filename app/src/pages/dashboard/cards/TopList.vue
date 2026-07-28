<script setup>
import AuroraCard from '../components/AuroraCard.vue';
import BarList from '../components/BarList.vue';

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  items: { type: Array, default: () => [] },
  emptyText: { type: String, default: 'No data in this range yet.' },
});
</script>

<template>
  <AuroraCard :title="title" :subtitle="subtitle">
    <BarList :items="items" :empty-text="emptyText" />
  </AuroraCard>
</template>
