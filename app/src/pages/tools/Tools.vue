<script setup>
const props = defineProps({
  serverData: { type: Object, required: true },
});
</script>

<template>
  <main class="tools-page">
    <header class="tools-page__header">
      <p class="tools-page__eyebrow">Aurora Admin</p>
      <h1>Tools</h1>
    </header>

    <div class="tools-page__grid">
      <!-- Core's Categories and Tags Converter card — not produced by a
           hook, so it's reproduced here directly (same visibility gate as
           native tools.php: the `import` capability plus category/tag
           manage_terms). -->
      <article v-if="serverData.showCategoriesConverter" class="card">
        <h2 class="title">Categories and Tags Converter</h2>
        <p>
          If you want to convert your categories to tags (or vice versa), use the
          <a :href="serverData.importUrl">Categories and Tags Converter</a> available from the Import screen.
        </p>
      </article>

      <!-- Rendered from WordPress's own `tool_box` hook — whatever other
           active plugins add there (WooCommerce, migration tools, etc.).
           Not Aurora-authored content, only Aurora-styled, so nothing
           another plugin adds to this screen silently disappears. -->
      <div v-if="serverData.nativeToolsHtml" class="tools-page__native" v-html="serverData.nativeToolsHtml" />
    </div>
    <p v-if="!serverData.showCategoriesConverter && !serverData.nativeToolsHtml" class="tools-page__empty">
      No tools are available right now.
    </p>
  </main>
</template>

<style scoped>
.tools-page { min-height: calc(100vh - 112px); color: var(--aurora-text); background: transparent; }
.tools-page__eyebrow {
  margin: 0 0 7px; color: var(--aurora-text-muted); font-size: 0.72rem;
  font-weight: 800; text-transform: uppercase;
}
.tools-page__header h1 { margin: 0 0 24px; font-size: 1.45rem; line-height: 1.15; }
.tools-page__empty { color: var(--aurora-text-muted); font-size: 0.875rem; }

.tools-page__grid {
  display: grid; gap: 16px;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
}
/* The v-html'd wrapper participates as if it weren't there, so cards inside
   it (there may be several, one per plugin hooked to tool_box) lay out in
   the same grid as Aurora's own card above instead of stacking inside one
   grid cell. */
.tools-page__native { display: contents; }

/* Native tool_box markup is `.card > h2.title + p` (core's own Categories &
   Tags Converter shape), but other plugins' cards may not match exactly —
   styled broadly by element, with .card/.title as the primary targets. The
   plain selectors below cover Aurora's own card (real template markup, gets
   Vue's scoping attribute automatically); :deep() additionally reaches into
   the v-html'd content, which the scoping attribute can't be applied to. */
.card, .tools-page__native :deep(.card) {
  background: var(--aurora-card-bg); border: 1px solid var(--aurora-card-border);
  border-radius: var(--aurora-radius-lg); padding: 20px 22px; margin: 0;
}
.card h2, .card h3, .card .title, .tools-page__native :deep(h2), .tools-page__native :deep(h3), .tools-page__native :deep(.title) {
  margin: 0 0 10px; font-size: 1rem; font-weight: 700; color: var(--aurora-text);
}
.card p, .tools-page__native :deep(p) {
  margin: 0; font-size: 0.8125rem; color: var(--aurora-text-muted); line-height: 1.6;
}
.card a, .tools-page__native :deep(a) { color: var(--aurora-accent); text-decoration: none; }
.card a:hover, .tools-page__native :deep(a:hover) { text-decoration: underline; }
</style>
