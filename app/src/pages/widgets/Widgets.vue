<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const api = (path) => `${props.serverData.restUrl}aurora-admin/v1${path}`;
const headers = () => ({ 'Content-Type': 'application/json', 'X-WP-Nonce': props.serverData.restNonce });

const sidebars = ref([]);
const loading = ref(true);
const error = ref('');

const load = async () => {
  loading.value = true;
  try {
    const res = await fetch(api('/widgets'), { headers: headers() });
    const data = await res.json();
    if (!res.ok) throw new Error(data?.message || 'Could not load widgets.');
    sidebars.value = data.sidebars || [];
  } catch (e) {
    error.value = e.message || 'Could not load widgets.';
  } finally {
    loading.value = false;
  }
};

onMounted(load);

const move = async (widget, fromSidebar, toSidebar, order = -1) => {
  await fetch(api('/widgets/move'), {
    method: 'POST',
    headers: headers(),
    body: JSON.stringify({ widgetId: widget.id, fromSidebar, toSidebar, order }),
  });
  await load();
};

const moveUp = (widget, sidebarId) => {
  if (widget.order <= 0) return;
  move(widget, sidebarId, sidebarId, widget.order - 1);
};
const moveDown = (widget, sidebarId, count) => {
  if (widget.order >= count - 1) return;
  move(widget, sidebarId, sidebarId, widget.order + 1);
};

const remove = async (widget) => {
  if (!confirm(`Delete "${widget.name}"? This can't be undone.`)) return;
  const res = await fetch(api(`/widgets/${encodeURIComponent(widget.id)}`), { method: 'DELETE', headers: headers() });
  const data = await res.json();
  if (!res.ok) {
    error.value = data?.message || 'Could not delete widget.';
    setTimeout(() => (error.value = ''), 3000);
    return;
  }
  await load();
};
</script>

<template>
  <main class="widgets-page">
    <header class="widgets-page__header">
      <div>
        <p class="widgets-page__eyebrow">Appearance</p>
        <h1>Widgets</h1>
        <p class="widgets-page__sub">
          Move widgets between your theme's sidebars, reorder them, or deactivate them. Adding a brand new
          widget type still uses WordPress's own block-based widgets screen.
        </p>
      </div>
    </header>

    <div v-if="error" class="widgets-notice widgets-notice--error">{{ error }}</div>
    <p v-if="loading" class="widgets-empty">Loading…</p>

    <div v-else class="widgets-grid">
      <section v-for="sidebar in sidebars" :key="sidebar.id" class="widgets-sidebar">
        <h2>{{ sidebar.name }}</h2>
        <p v-if="!sidebar.widgets.length" class="widgets-empty">No widgets here.</p>

        <div v-for="widget in sidebar.widgets" :key="widget.id" class="widgets-item">
          <div class="widgets-item__info">
            <strong>{{ widget.name }}</strong>
            <span v-if="widget.title">{{ widget.title }}</span>
          </div>
          <div class="widgets-item__actions">
            <button type="button" title="Move up" @click="moveUp(widget, sidebar.id)"><span class="dashicons dashicons-arrow-up-alt2" /></button>
            <button type="button" title="Move down" @click="moveDown(widget, sidebar.id, sidebar.widgets.length)"><span class="dashicons dashicons-arrow-down-alt2" /></button>
            <select :value="sidebar.id" @change="move(widget, sidebar.id, $event.target.value)">
              <option v-for="s in sidebars" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <button v-if="sidebar.id === 'wp_inactive_widgets'" type="button" title="Delete" @click="remove(widget)">
              <span class="dashicons dashicons-trash" />
            </button>
          </div>
        </div>
      </section>
    </div>
  </main>
</template>

<style scoped>
.widgets-page { max-width: 1100px; margin: 0 auto; }
.widgets-page__header { margin-bottom: 20px; }
.widgets-page__eyebrow {
  margin: 0 0 4px; font-size: 0.75rem; text-transform: uppercase;
  letter-spacing: 0.06em; color: var(--aurora-text-muted);
}
.widgets-page h1 { margin: 0 0 6px; font-size: 1.6rem; font-weight: 700; color: var(--aurora-text); }
.widgets-page__sub { margin: 0; font-size: 0.8125rem; color: var(--aurora-text-muted); max-width: 640px; }

.widgets-notice { padding: 10px 14px; border-radius: var(--aurora-radius-sm); margin-bottom: 16px; font-size: 0.875rem; }
.widgets-notice--error { background: rgba(239, 68, 68, 0.12); color: #ef4444; }

.widgets-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
.widgets-sidebar {
  background: var(--aurora-bg-subtle); border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-md); padding: 18px;
}
.widgets-sidebar h2 { font-size: 0.9375rem; margin: 0 0 10px; color: var(--aurora-text); }
.widgets-empty { color: var(--aurora-text-muted); font-size: 0.8125rem; }

.widgets-item {
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  padding: 8px 10px; border-radius: var(--aurora-radius-sm); margin-bottom: 6px;
  background: var(--aurora-bg); border: 1px solid var(--aurora-border);
  font-size: 0.8125rem;
}
.widgets-item__info { display: flex; flex-direction: column; min-width: 0; }
.widgets-item__info strong { color: var(--aurora-text); }
.widgets-item__info span { color: var(--aurora-text-muted); font-size: 0.75rem; }
.widgets-item__actions { display: flex; align-items: center; gap: 2px; flex-shrink: 0; }
.widgets-item__actions button {
  border: none; background: none; color: var(--aurora-text-muted); cursor: pointer;
  width: 26px; height: 26px; border-radius: var(--aurora-radius-sm); display: inline-flex; align-items: center; justify-content: center;
}
.widgets-item__actions button:hover { background: var(--aurora-bg-subtle); color: var(--aurora-text); }
.widgets-item__actions select {
  font-size: 0.75rem; padding: 4px 6px; border-radius: var(--aurora-radius-sm);
  border: 1px solid var(--aurora-border); background: var(--aurora-bg); color: var(--aurora-text);
}
</style>
