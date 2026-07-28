<script setup>
import { ref, onMounted } from 'vue';
import Sidebar from './components/Sidebar.vue';
import Toolbar from './components/Toolbar.vue';
import { applyThemePalette, applyFontFamily } from '@/pages/settings/applyLiveTheme.js';
import { initDashboardFrame } from './dashboardFrame.js';

const props = defineProps({
  serverData: {
    type: Object,
    required: true,
  },
});

// Minified (icon-only) menu — a per-browser preference kept in
// localStorage; the html class drives all layout via --aurora-sidebar-w.
const collapsed = ref(localStorage.getItem('aurora-menu-collapsed') === '1');
const applyCollapsed = () => {
  document.documentElement.classList.toggle('aurora-collapsed', collapsed.value);
};
const toggleMenu = () => {
  collapsed.value = !collapsed.value;
  applyCollapsed();
  localStorage.setItem('aurora-menu-collapsed', collapsed.value ? '1' : '0');
};

onMounted(() => {
  const settings = props.serverData?.settings || {};
  // Every theme preset is its own self-contained dark-toned palette now —
  // no separate light/dark mode, so this class is always on. It's kept
  // (rather than renamed) because shell-frame.css and native-dark.css both
  // still gate their rules on it.
  document.documentElement.classList.add('aurora-dark');
  applyThemePalette(settings.theme_preset || 'indigo');
  applyCollapsed();
  applyFontFamily(settings.font_family);
  initDashboardFrame();
  // Signal the shell is live: reveals the framed content and commits to
  // keeping native chrome hidden (the PHP failsafe stops watching once
  // aurora-ready is present).
  document.documentElement.classList.add('aurora-ready');
});
</script>

<template>
  <div class="site-shell">
    <Sidebar
      :menu="serverData.menu"
      :aurora-nav="serverData.auroraNav"
      :current-url="serverData.currentUrl"
      :active-parent-file="serverData.activeParentFile || ''"
      :logo="serverData.settings?.logo || ''"
      :dark-logo="serverData.settings?.dark_logo || ''"
      :collapsed="collapsed"
      :server-data="serverData"
    />
    <Toolbar
      :server-data="serverData"
      @toggle-menu="toggleMenu"
    />
  </div>
</template>

<style scoped>
/* Sidebar and Toolbar are both position:fixed, so this wrapper has no
   layout role — it just groups them under one mounted root. */
.site-shell {
  display: contents;
}
</style>
