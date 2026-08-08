<script setup>
import { ref, computed, onMounted } from 'vue';
import { useDashboardData } from './useDashboardData.js';
import { useAnalyticsData } from './useAnalyticsData.js';
import RecentContent from './cards/RecentContent.vue';
import ScheduledContent from './cards/ScheduledContent.vue';
import RecentComments from './cards/RecentComments.vue';
import UserAnalytics from './cards/UserAnalytics.vue';
import MediaAnalytics from './cards/MediaAnalytics.vue';
import ServerHealth from './cards/ServerHealth.vue';
import PageViews from './cards/PageViews.vue';
import ActiveUsers from './cards/ActiveUsers.vue';
import DeviceUsage from './cards/DeviceUsage.vue';
import Engagement from './cards/Engagement.vue';
import TopList from './cards/TopList.vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const { data, loading, load } = useDashboardData(props.serverData);
const { data: analytics, error: analyticsError, load: loadAnalytics } = useAnalyticsData(props.serverData);

// Date range — default last 7 days, with simple presets.
const today = new Date();
const range = ref([new Date(new Date().setDate(today.getDate() - 7)), new Date()]);
const presetsOpen = ref(false);

const fmtLabel = (d) =>
  d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });

const reload = (from, to) => {
  load(from, to);
  loadAnalytics(from, to);
};

const applyPreset = (days) => {
  const to = new Date();
  const from = new Date();
  from.setDate(to.getDate() - days);
  range.value = [from, to];
  presetsOpen.value = false;
  reload(from, to);
};

const activeTab = ref('overview');

onMounted(() => reload(range.value[0], range.value[1]));
</script>

<template>
  <div class="aurora-dashboard">
    <header class="dash__head">
      <div>
        <h1 class="dash__title">Dashboard</h1>
        <p class="dash__subtitle">Overview of your site activity</p>
      </div>

      <div class="dash__range">
        <button type="button" class="dash__range-btn" @click="presetsOpen = !presetsOpen">
          <span class="dash__range-icon">▦</span>
          {{ fmtLabel(range[0]) }} — {{ fmtLabel(range[1]) }}
        </button>
        <div v-if="presetsOpen" class="dash__presets">
          <button type="button" @click="applyPreset(7)">Last 7 days</button>
          <button type="button" @click="applyPreset(30)">Last 30 days</button>
          <button type="button" @click="applyPreset(90)">Last 90 days</button>
        </div>
      </div>
    </header>

    <div class="dash__tabs">
      <button
        type="button"
        :class="['dash__tab', { 'dash__tab--active': activeTab === 'overview' }]"
        @click="activeTab = 'overview'"
      >
        Overview
      </button>
      <button
        type="button"
        :class="['dash__tab', { 'dash__tab--active': activeTab === 'analytics' }]"
        @click="activeTab = 'analytics'"
      >
        Analytics
      </button>
    </div>

    <div v-if="activeTab === 'overview'" class="dash__grid">
      <div class="dash__cell" style="grid-column: span 4">
        <RecentContent :data="data ? data.recentContent : {}" />
      </div>
      <div class="dash__cell" style="grid-column: span 4">
        <ScheduledContent :data="data ? data.scheduledContent : {}" />
      </div>
      <div class="dash__cell" style="grid-column: span 4">
        <RecentComments :data="data ? data.recentComments : {}" />
      </div>
      <div class="dash__cell" style="grid-column: span 6">
        <UserAnalytics :data="data ? data.userAnalytics : {}" />
      </div>
      <div class="dash__cell" style="grid-column: span 6">
        <MediaAnalytics :data="data ? data.mediaAnalytics : {}" />
      </div>
      <div class="dash__cell" style="grid-column: span 6">
        <ServerHealth :data="data ? data.serverHealth : {}" />
      </div>
    </div>

    <div v-else-if="analyticsError" class="dash__analytics-error">
      Could not load analytics. Try refreshing the page.
    </div>

    <div v-else class="dash__grid">
      <div class="dash__cell" style="grid-column: span 8">
        <PageViews :data="analytics ? analytics.pageViews : {}" />
      </div>
      <div class="dash__cell" style="grid-column: span 4">
        <ActiveUsers :count="analytics ? analytics.activeUsers : 0" />
      </div>
      <div class="dash__cell" style="grid-column: span 4">
        <DeviceUsage :items="analytics ? analytics.deviceUsage : []" />
      </div>
      <div class="dash__cell" style="grid-column: span 4">
        <Engagement :bounce="analytics ? analytics.bounce : {}" />
      </div>
      <div class="dash__cell" style="grid-column: span 4">
        <TopList
          title="Top Countries"
          subtitle="Where visitors are from"
          :items="analytics ? analytics.topCountries : []"
          empty-text="No location data yet."
        />
      </div>
      <div class="dash__cell" style="grid-column: span 6">
        <TopList
          title="Top Pages"
          subtitle="Most viewed pages"
          :items="analytics ? analytics.topPages : []"
        />
      </div>
      <div class="dash__cell" style="grid-column: span 6">
        <TopList
          title="Top Referrers"
          subtitle="Where traffic comes from"
          :items="analytics ? analytics.topReferrers : []"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.aurora-dashboard {
  padding: 24px var(--aurora-gutter-r, 44px) 40px 32px;
  box-sizing: border-box;
}
.dash__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
}
.dash__title {
  margin: 0;
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--aurora-text);
  letter-spacing: -0.02em;
}
.dash__subtitle { margin: 4px 0 0; font-size: 0.875rem; color: var(--aurora-text-muted); }
.dash__range { position: relative; }
.dash__range-btn {
  display: flex; align-items: center; gap: 8px;
  background: var(--aurora-card-bg);
  border: 1px solid var(--aurora-card-border);
  color: var(--aurora-text);
  border-radius: var(--aurora-radius-md);
  padding: 8px 12px;
  font-size: 0.8125rem;
  cursor: pointer;
}
.dash__range-icon { color: var(--aurora-text-muted); }
.dash__presets {
  position: absolute; right: 0; top: calc(100% + 6px);
  background: var(--aurora-card-bg);
  border: 1px solid var(--aurora-card-border);
  border-radius: var(--aurora-radius-md);
  padding: 6px;
  z-index: 10;
  min-width: 160px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.25);
}
.dash__presets button {
  display: block; width: 100%; text-align: left;
  background: none; border: none; color: var(--aurora-text);
  padding: 8px 10px; border-radius: var(--aurora-radius-sm); cursor: pointer; font-size: 0.8125rem;
}
.dash__presets button:hover { background: var(--aurora-bg-subtle); }
.dash__tabs {
  display: inline-flex;
  gap: 2px;
  background: var(--aurora-bg-subtle);
  border-radius: var(--aurora-radius-md);
  padding: 3px;
  margin-bottom: 20px;
}
.dash__analytics-error {
  padding: 48px 24px;
  text-align: center;
  color: var(--aurora-text-muted);
  font-size: 0.875rem;
}
.dash__tab {
  border: none; background: none; cursor: pointer;
  padding: 6px 16px; border-radius: var(--aurora-radius-sm);
  font-size: 0.8125rem; font-weight: 500;
  color: var(--aurora-text-muted);
}
.dash__tab--active { background: var(--aurora-card-bg); color: var(--aurora-text); }
.dash__grid {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 18px;
}
.dash__cell { min-width: 0; display: flex; }
.dash__cell > * { width: 100%; }
.dash__analytics-empty {
  color: var(--aurora-text-muted);
  font-size: 0.875rem;
  padding: 40px 0;
  text-align: center;
}
@media (max-width: 1100px) {
  .dash__cell { grid-column: span 12 !important; }
}
</style>

<style>
/* Shared bits used inside the card slots (head-right badges, footer links).
   Scoped under .aurora-dashboard so they can't collide with WordPress or
   leak elsewhere, while still reaching slotted content in child cards. */
.aurora-dashboard .dot {
  display: inline-block;
  width: 7px; height: 7px;
  border-radius: 50%;
  margin-right: 4px;
  vertical-align: middle;
}
.aurora-dashboard .dot--accent { background: var(--aurora-accent); }
.aurora-dashboard .dot--green { background: #34d399; }
.aurora-dashboard .viewall {
  font-size: 0.75rem;
  color: var(--aurora-text-muted);
  text-decoration: none;
}
.aurora-dashboard .viewall:hover { color: var(--aurora-accent); }
</style>
