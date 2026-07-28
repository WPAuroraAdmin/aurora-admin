<script setup>
import AuroraCard from '../components/AuroraCard.vue';
import StatRow from '../components/StatRow.vue';

defineProps({ data: { type: Object, default: () => ({}) } });
</script>

<template>
  <AuroraCard title="Recent Comments" subtitle="Latest comments and discussions">
    <template #head-right>
      <span class="dot dot--accent" /> {{ data.total || 0 }} total
    </template>

    <StatRow
      :stats="[
        { value: data.approved || 0, label: 'Approved' },
        { value: data.pending || 0, label: 'Pending' },
        { value: data.spam || 0, label: 'Spam' },
      ]"
    />

    <ul class="cm__list">
      <li v-for="c in data.latest" :key="c.id" class="cm__item">
        <p class="cm__excerpt">{{ c.excerpt }}</p>
        <div class="cm__meta">on <span class="cm__post">{{ c.postTitle }}</span> · {{ c.date }}</div>
      </li>
      <li v-if="!data.latest || !data.latest.length" class="cm__empty">No comments yet.</li>
    </ul>

    <template #footer>
      <a :href="data.listUrl" class="viewall">View all ›</a>
    </template>
  </AuroraCard>
</template>

<style scoped>
.cm__list { list-style: none; margin: 16px 0 0; padding: 0; }
.cm__item { padding: 8px 0; }
.cm__excerpt { margin: 0; font-size: 0.8125rem; color: var(--aurora-text); }
.cm__meta { margin-top: 4px; font-size: 0.75rem; color: var(--aurora-text-muted); }
.cm__post { color: var(--aurora-accent); }
.cm__empty { font-size: 0.8125rem; color: var(--aurora-text-muted); padding: 12px 0; }
</style>
