<script setup>
defineProps({
  title: { type: String, required: true },
  searchPlaceholder: { type: String, default: 'Search…' },
  countLabel: { type: String, default: '' },
  page: { type: Number, default: 1 },
  hasPrev: { type: Boolean, default: false },
  hasNext: { type: Boolean, default: false },
  // Right-panel placeholder, shown until something is selected.
  detailIcon: { type: String, default: 'dashicons-admin-generic' },
  detailTitle: { type: String, default: '' },
  detailText: { type: String, default: '' },
  hasSelection: { type: Boolean, default: false },
});

defineEmits(['prev-page', 'next-page']);

const search = defineModel('search', { type: String, default: '' });
</script>

<template>
  <div class="app-list-shell">
    <!-- Left: list panel -->
    <aside class="app-list-shell__nav">
      <div class="app-list-shell__nav-head">
        <h1 class="app-list-shell__title">{{ title }}</h1>
        <div class="app-list-shell__actions">
          <slot name="header-actions" />
        </div>
      </div>

      <div class="app-list-shell__search">
        <span class="dashicons dashicons-search" />
        <input v-model="search" type="text" :placeholder="searchPlaceholder" />
      </div>

      <div v-if="$slots.filters" class="app-list-shell__filters">
        <slot name="filters" />
      </div>

      <div v-if="countLabel || hasPrev || hasNext" class="app-list-shell__meta">
        <span>{{ countLabel }}</span>
        <span v-if="hasPrev || hasNext" class="app-list-shell__pager">
          Page {{ page }}
          <button type="button" :disabled="!hasPrev" @click="$emit('prev-page')">‹</button>
          <button type="button" :disabled="!hasNext" @click="$emit('next-page')">›</button>
        </span>
      </div>

      <div class="app-list-shell__list">
        <slot name="list" />
      </div>
    </aside>

    <!-- Right: detail panel -->
    <section class="app-list-shell__content">
      <slot v-if="hasSelection" name="detail" />
      <div v-else class="app-list-shell__empty">
        <span class="app-list-shell__empty-icon">
          <span class="dashicons" :class="detailIcon" />
        </span>
        <h2 class="app-list-shell__empty-title">{{ detailTitle }}</h2>
        <p class="app-list-shell__empty-text">{{ detailText }}</p>
        <slot name="detail-empty-actions" />
      </div>
    </section>
  </div>
</template>

<style scoped>
.app-list-shell {
  display: flex;
  align-items: flex-start;
  gap: 20px;
  /* A fixed height (not just min-height) so the two panes below can each
     scroll independently within it — previously this only set a minimum
     and let the whole shell grow with content, meaning both panes shared
     one page-level scrollbar. Selecting an item near the bottom of a long
     list left the detail panel's newly-loaded content rendered above the
     current scroll position, looking like nothing happened until you
     scrolled all the way back up. */
  height: calc(100vh - var(--aurora-toolbar-h) - 60px);
  /* -20px cancels the scroller's 20px padding (shell-frame.css's two-layer
     scroll rule) so the shell still bleeds to the rounded frame's inner
     edge. Was -32px back when #wpbody-content padded the content directly. */
  margin: -20px;
  padding: 22px;
  box-sizing: border-box;
  /* No background — html/body already paint the page gradient; this was
     a redundant duplicate layer on top of it. */
}

/* Left — no background/border of its own; the page gradient shows
   straight through so it reads as one surface instead of a boxed panel. */
.app-list-shell__nav {
  width: 340px;
  flex-shrink: 0;
  height: 100%;
  overflow-y: auto;
  border-radius: var(--aurora-radius-lg);
  padding: 24px 20px;
  box-sizing: border-box;
}
.app-list-shell__nav-head {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 18px;
}
.app-list-shell__title { font-size: 1.35rem; font-weight: 700; margin: 0; color: var(--aurora-text); }
.app-list-shell__actions { display: flex; gap: 6px; align-items: center; }
.app-list-shell__actions :deep(button) {
  display: inline-flex; align-items: center; justify-content: center;
  height: 30px; padding: 0 10px; border: none; border-radius: var(--aurora-radius-sm);
  background: var(--aurora-bg-subtle); color: var(--aurora-text-muted);
  font-size: 0.75rem; font-weight: 500; cursor: pointer; gap: 6px;
}
.app-list-shell__actions :deep(button.is-primary) {
  background: var(--aurora-accent); color: #fff;
}
.app-list-shell__actions :deep(button):hover { opacity: 0.85; }

.app-list-shell__search {
  display: flex; align-items: center; gap: 8px;
  background: var(--aurora-bg-subtle);
  border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-md); padding: 8px 12px; margin-bottom: 14px;
  color: var(--aurora-text-muted);
}
.app-list-shell__search input {
  border: none; background: none; outline: none; color: var(--aurora-text);
  font-size: 0.8125rem; width: 100%;
}

.app-list-shell__filters {
  display: flex; gap: 6px; margin-bottom: 14px; flex-wrap: wrap;
}
.app-list-shell__filters :deep(button) {
  border: none; border-radius: 999px; padding: 5px 12px;
  font-size: 0.75rem; font-weight: 500; cursor: pointer;
  background: var(--aurora-bg-subtle); color: var(--aurora-text-muted);
}
.app-list-shell__filters :deep(button.is-active) {
  /* --aurora-app-bg can be a gradient (invalid for `color`), so this one
     inverted-contrast badge uses the flat fallback token instead. */
  background: var(--aurora-text); color: var(--aurora-app-bg-solid);
}

.app-list-shell__meta {
  display: flex; align-items: center; justify-content: space-between;
  font-size: 0.6875rem; font-weight: 600; letter-spacing: 0.03em;
  text-transform: uppercase; color: var(--aurora-text-muted);
  margin-bottom: 8px; padding: 0 2px;
}
.app-list-shell__pager { display: flex; align-items: center; gap: 4px; text-transform: none; }
.app-list-shell__pager button {
  border: none; background: none; color: var(--aurora-text-muted);
  cursor: pointer; font-size: 0.9rem; padding: 0 2px;
}
.app-list-shell__pager button:disabled { opacity: 0.3; cursor: default; }

.app-list-shell__list { display: flex; flex-direction: column; gap: 2px; }

/* Right — no background/border of its own, same reasoning. */
.app-list-shell__content {
  flex: 1; min-width: 0;
  height: 100%;
  overflow-y: auto;
  border-radius: var(--aurora-radius-lg);
  padding: 28px 32px;
  min-height: 480px;
  box-sizing: border-box;
}

.app-list-shell__empty {
  height: 100%;
  min-height: 420px;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  text-align: center; gap: 6px;
}
.app-list-shell__empty-icon {
  width: 56px; height: 56px; border-radius: var(--aurora-radius-md);
  background: var(--aurora-bg-subtle); color: var(--aurora-text-muted);
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; margin-bottom: 10px;
}
.app-list-shell__empty-title { font-size: 1.05rem; font-weight: 700; margin: 0; color: var(--aurora-text); }
.app-list-shell__empty-text { font-size: 0.8125rem; color: var(--aurora-text-muted); max-width: 320px; margin: 0; }

@media (max-width: 1000px) {
  /* Stacked instead of side-by-side, so the fixed-height/independent-
     scroll treatment above (built for two side-by-side panes) would just
     squash both panes into half-height boxes — switch to natural height
     with each pane capped and independently scrollable instead. */
  .app-list-shell { flex-direction: column; height: auto; }
  .app-list-shell__nav {
    width: auto;
    height: auto;
    max-height: 40vh;
  }
  .app-list-shell__content {
    height: auto;
    max-height: 60vh;
  }
}
</style>
