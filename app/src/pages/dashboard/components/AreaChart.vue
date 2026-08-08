<script setup>
import { computed } from 'vue';

// series: [{ date, count }]
const props = defineProps({
  series: { type: Array, default: () => [] },
  height: { type: Number, default: 140 },
});

const W = 600;
const PAD = 10;

const view = computed(() => {
  const s = props.series;
  if (!s.length) return null;
  const max = Math.max(1, ...s.map((d) => d.count));
  const H = props.height;
  const innerW = W - PAD * 2;
  const innerH = H - PAD * 2;
  const stepX = s.length > 1 ? innerW / (s.length - 1) : 0;
  const points = s.map((d, i) => {
    const x = PAD + i * stepX;
    const y = PAD + innerH - (d.count / max) * innerH;
    return [x, y];
  });
  const line = points.map((p, i) => `${i === 0 ? 'M' : 'L'}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(' ');
  const area = `${line} L${points[points.length - 1][0].toFixed(1)},${(H - PAD).toFixed(1)} L${points[0][0].toFixed(1)},${(H - PAD).toFixed(1)} Z`;
  return { points, line, area, H };
});
</script>

<template>
  <div class="areachart" v-if="view">
    <svg :viewBox="`0 0 ${W} ${view.H}`" preserveAspectRatio="none" class="areachart__svg">
      <defs>
        <linearGradient id="aurora-area-grad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="var(--aurora-accent)" stop-opacity="0.35" />
          <stop offset="100%" stop-color="var(--aurora-accent)" stop-opacity="0" />
        </linearGradient>
      </defs>
      <path :d="view.area" fill="url(#aurora-area-grad)" />
      <path :d="view.line" fill="none" stroke="var(--aurora-accent)" stroke-width="2"
        vector-effect="non-scaling-stroke" />
      <circle v-for="(p, i) in view.points" :key="i" :cx="p[0]" :cy="p[1]" r="3"
        fill="var(--aurora-accent)" vector-effect="non-scaling-stroke" />
    </svg>
    <div class="areachart__labels">
      <span v-for="(d, i) in series" :key="i">{{ d.date }}</span>
    </div>
  </div>
</template>

<style scoped>
.areachart__svg {
  width: 100%;
  display: block;
}
.areachart__labels {
  display: flex;
  justify-content: space-between;
  margin-top: 6px;
  font-size: 0.6875rem;
  color: var(--aurora-text-muted);
}
.areachart__labels span {
  flex: 1;
  text-align: center;
}
.areachart__labels span:first-child { text-align: left; }
.areachart__labels span:last-child { text-align: right; }
</style>
