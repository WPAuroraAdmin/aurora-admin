<script setup>
import AuroraCard from '../components/AuroraCard.vue';
import StatRow from '../components/StatRow.vue';

const props = defineProps({ data: { type: Object, default: () => ({}) } });

const statusLabel = (s) =>
  ({ publish: 'Published', draft: 'Draft', future: 'Scheduled' }[s] || s);
</script>

<template>
  <AuroraCard title="Recent content" subtitle="Latest content activity">
    <template #head-right>
      <span class="dot dot--accent" /> {{ data.total || 0 }} total
    </template>

    <StatRow
      :stats="[
        { value: data.published || 0, label: 'Published' },
        { value: data.draft || 0, label: 'Draft' },
        { value: data.scheduled || 0, label: 'Scheduled' },
      ]"
    />

    <ul class="rc__list">
      <li v-for="p in data.latest" :key="p.id" class="rc__item">
        <div class="rc__main">
          <a :href="p.editUrl" class="rc__title">{{ p.title || '(no title)' }}</a>
          <div class="rc__meta">{{ p.author }} · {{ p.date }}</div>
        </div>
        <span class="rc__badge" :class="`rc__badge--${p.status}`">
          {{ statusLabel(p.status) }}
        </span>
      </li>
      <li v-if="!data.latest || !data.latest.length" class="rc__empty">No content yet.</li>
    </ul>

    <template #footer>
      <a :href="data.listUrl" class="viewall">View all ›</a>
    </template>
  </AuroraCard>
</template>

<style scoped>
.rc__list { list-style: none; margin: 16px 0 0; padding: 0; }
.rc__item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 0;
}
.rc__title {
  color: var(--aurora-text);
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
}
.rc__title:hover { color: var(--aurora-accent); }
.rc__meta { font-size: 0.75rem; color: var(--aurora-text-muted); margin-top: 2px; }
.rc__badge {
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  flex-shrink: 0;
}
.rc__badge--publish { color: #34d399; }
.rc__badge--future { color: var(--aurora-accent); }
.rc__badge--draft { color: var(--aurora-text-muted); }
.rc__empty { font-size: 0.8125rem; color: var(--aurora-text-muted); padding: 12px 0; }
</style>
