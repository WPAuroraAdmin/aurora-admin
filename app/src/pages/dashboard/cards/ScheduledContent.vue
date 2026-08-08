<script setup>
import { ref, computed } from 'vue';
import AuroraCard from '../components/AuroraCard.vue';

const props = defineProps({ data: { type: Object, default: () => ({}) } });

const view = ref(new Date());

const monthLabel = computed(() =>
  view.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
);

const weekdays = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];

// Build a calendar grid (Mon-first) for the viewed month.
const grid = computed(() => {
  const year = view.value.getFullYear();
  const month = view.value.getMonth();
  const first = new Date(year, month, 1);
  const startOffset = (first.getDay() + 6) % 7; // Mon = 0
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const cells = [];
  for (let i = 0; i < startOffset; i++) cells.push(null);
  const today = new Date();
  for (let d = 1; d <= daysInMonth; d++) {
    const key = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    const scheduled = props.data.days && props.data.days[key] ? props.data.days[key].length : 0;
    const isToday =
      d === today.getDate() && month === today.getMonth() && year === today.getFullYear();
    cells.push({ d, key, scheduled, isToday });
  }
  return cells;
});

const move = (delta) => {
  const next = new Date(view.value);
  next.setMonth(next.getMonth() + delta);
  view.value = next;
};

// Clicking a day: with scheduled content, open the posts list filtered to
// that day; otherwise show a transient popup on the dashboard.
const toast = ref('');
let toastTimer;
const onDayClick = (cell) => {
  if (!cell) return;
  if (cell.scheduled > 0) {
    // Relative to /wp-admin/ (the dashboard is at index.php).
    window.location.href = `edit.php?post_status=future&aurora_day=${cell.key}`;
  } else {
    toast.value = 'No scheduled content for this day.';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (toast.value = ''), 3000);
  }
};
</script>

<template>
  <AuroraCard title="Scheduled Content" :subtitle="monthLabel">
    <template #head-right>
      <div class="sc__nav">
        <button type="button" @click="move(-1)" aria-label="Previous month">‹</button>
        <button type="button" @click="move(1)" aria-label="Next month">›</button>
      </div>
    </template>

    <div class="sc__count">
      <span class="sc__count-num">{{ data.count || 0 }}</span> Scheduled Content
    </div>

    <div class="sc__weekdays">
      <span v-for="(w, i) in weekdays" :key="i">{{ w }}</span>
    </div>
    <div class="sc__grid">
      <div v-for="(cell, i) in grid" :key="i" class="sc__cell">
        <button
          v-if="cell"
          type="button"
          class="sc__day"
          :class="{ 'sc__day--has': cell.scheduled > 0, 'sc__day--today': cell.isToday }"
          :title="cell.scheduled > 0 ? `${cell.scheduled} scheduled` : 'No scheduled content'"
          @click="onDayClick(cell)"
        >
          <span class="sc__date">{{ cell.d }}</span>
          <span v-if="cell.scheduled > 0" class="sc__count">{{ cell.scheduled }}</span>
        </button>
      </div>
    </div>

    <Teleport to="body">
      <Transition name="sc-toast">
        <div v-if="toast" class="sc__toast">{{ toast }}</div>
      </Transition>
    </Teleport>
  </AuroraCard>
</template>

<style scoped>
.sc__nav { display: flex; gap: 4px; }
.sc__nav button {
  width: 26px; height: 26px;
  border: 1px solid var(--aurora-card-border);
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text-muted);
  border-radius: var(--aurora-radius-sm);
  cursor: pointer;
  line-height: 1;
}
.sc__nav button:hover { color: var(--aurora-text); }
.sc__count { font-size: 0.875rem; color: var(--aurora-text-muted); margin-bottom: 16px; }
.sc__count-num { font-size: 1.5rem; font-weight: 700; color: var(--aurora-text); margin-right: 6px; }
.sc__weekdays {
  display: grid; grid-template-columns: repeat(7, 1fr);
  font-size: 0.6875rem; color: var(--aurora-text-muted); margin-bottom: 8px;
}
.sc__weekdays span { text-align: center; }
.sc__grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
.sc__cell { aspect-ratio: 1; }
.sc__day {
  position: relative;
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  border-radius: var(--aurora-radius-sm);
  border: 1px solid var(--aurora-card-border);
  background: transparent;
  padding: 0;
  cursor: pointer;
  font-family: inherit;
  transition: border-color 0.12s ease, background-color 0.12s ease;
}
.sc__day:hover { border-color: var(--aurora-accent); }
.sc__day--has:hover { filter: brightness(1.08); }
.sc__date {
  position: absolute; top: 5px; left: 6px;
  font-size: 0.7rem; line-height: 1; color: var(--aurora-text-muted);
}
.sc__count {
  font-size: 1.05rem; font-weight: 700; color: var(--aurora-text);
}
.sc__day--has { background: var(--aurora-accent); border-color: var(--aurora-accent); }
.sc__day--has .sc__date { color: rgba(255, 255, 255, 0.85); }
.sc__day--has .sc__count { color: #fff; }
.sc__day--today { border-color: var(--aurora-accent); }
.sc__day--today .sc__date { color: var(--aurora-accent); }
</style>

<style>
/* Toast is teleported to <body>, so these rules are global (not scoped).
   They rely on the global --aurora-* theme variables. */
.sc__toast {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 100001;
  background: var(--aurora-card-bg, #1f1f23);
  color: var(--aurora-text, #f4f4f5);
  border: 1px solid var(--aurora-border, #2e2e33);
  border-radius: var(--aurora-radius-md);
  padding: 12px 18px;
  font-size: 0.875rem;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  box-shadow: 0 8px 28px rgba(0, 0, 0, 0.35);
}
.sc-toast-enter-active,
.sc-toast-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.sc-toast-enter-from,
.sc-toast-leave-to { opacity: 0; transform: translate(-50%, 10px); }
</style>
