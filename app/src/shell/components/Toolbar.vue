<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import SearchModal from './SearchModal.vue';

const props = defineProps({
  serverData: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['toggle-menu']);

const searchOpen = ref(false);

// Settings > General > "Disable global search" hides the search entirely.
const searchDisabled = computed(
  () => !!props.serverData.settings?.disable_global_search
);

const onKeydown = (e) => {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
    if (searchDisabled.value) return;
    e.preventDefault();
    searchOpen.value = true;
  }
};
onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
  <header class="toolbar">
    <div class="toolbar__left">
      <button
        type="button"
        class="toolbar__icon-btn"
        title="Toggle menu"
        @click="emit('toggle-menu')"
      >
        <span class="dashicons dashicons-menu" />
      </button>
      <a
        :href="serverData.siteUrl || '/'"
        class="toolbar__home"
        title="View website"
        target="_blank"
        rel="noopener noreferrer"
      >
        <span class="dashicons dashicons-admin-home" />
      </a>
      <a :href="serverData.adminUrl" class="toolbar__home" title="Dashboard">
        <span class="dashicons dashicons-dashboard" />
      </a>
      <button
        v-if="!searchDisabled"
        type="button"
        class="toolbar__search"
        @click="searchOpen = true"
      >
        <span class="dashicons dashicons-search toolbar__search-icon" />
        <span class="toolbar__search-text">Search…</span>
        <span class="toolbar__search-kbd">Ctrl K</span>
      </button>
    </div>

    <SearchModal
      :server-data="serverData"
      :open="searchOpen"
      @close="searchOpen = false"
    />
  </header>
</template>

<style scoped>
.toolbar {
  position: fixed;
  top: 0;
  left: calc(var(--aurora-sidebar-w) + var(--aurora-sidebar-gap) * 2);
  right: 0;
  height: var(--aurora-toolbar-h);
  /* No background — see Sidebar.vue; the fixed html/body gradient shows
     through so the whole shell reads as one continuous background. */
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  z-index: 99998;
  box-sizing: border-box;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.toolbar__left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.toolbar__home {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: var(--aurora-radius-sm);
  color: var(--aurora-text-muted);
  text-decoration: none;
}

.toolbar__home:hover {
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
}

.toolbar__search {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 34px;
  padding: 0 10px;
  min-width: 240px;
  border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-md);
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text-muted);
  font-size: 0.8125rem;
  cursor: text;
}

.toolbar__search-icon {
  font-size: 16px;
  width: 16px;
  height: 16px;
}

.toolbar__search-text {
  flex: 1;
  text-align: left;
}

.toolbar__search-kbd {
  font-size: 0.6875rem;
  border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-sm);
  padding: 1px 6px;
  background: var(--aurora-bg);
}

.toolbar__icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border: none;
  background: none;
  border-radius: var(--aurora-radius-sm);
  cursor: pointer;
  color: var(--aurora-text-muted);
}

.toolbar__icon-btn:hover {
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
}

</style>
