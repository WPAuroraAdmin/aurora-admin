<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const plugins = ref([]);
const stats = ref({ total: 0, active: 0, inactive: 0, updates: 0 });
const addUrl = ref('');
const loading = ref(true);
const busyFile = ref('');
const error = ref('');
const notice = ref('');
const search = ref('');
const status = ref('all');
const compact = ref(false);

const apiBase = computed(() => `${props.serverData.restUrl}aurora-admin/v1`);

const request = async (path, options = {}) => {
  const response = await fetch(`${apiBase.value}${path}`, {
    credentials: 'same-origin',
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': props.serverData.restNonce,
      ...(options.headers || {}),
    },
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data.message || data.error || 'Request failed');
  }
  return data;
};

const load = async () => {
  loading.value = true;
  error.value = '';
  try {
    const data = await request('/plugins');
    plugins.value = data.items || [];
    stats.value = data.stats || stats.value;
    addUrl.value = data.addUrl || '';
  } catch (err) {
    error.value = err.message || 'Could not load plugins';
  } finally {
    loading.value = false;
  }
};

onMounted(load);

const filtered = computed(() => {
  const query = search.value.trim().toLowerCase();
  return plugins.value.filter((plugin) => {
    const matchesStatus =
      status.value === 'all' ||
      (status.value === 'active' && plugin.active) ||
      (status.value === 'inactive' && !plugin.active) ||
      (status.value === 'updates' && plugin.updateAvailable);
    if (!matchesStatus) return false;
    if (!query) return true;
    return [plugin.name, plugin.description, plugin.author, plugin.file]
      .join(' ')
      .toLowerCase()
      .includes(query);
  });
});

const filters = computed(() => [
  { key: 'all', label: 'All', count: stats.value.total },
  { key: 'active', label: 'Active', count: stats.value.active },
  { key: 'inactive', label: 'Inactive', count: stats.value.inactive },
  { key: 'updates', label: 'Update Available', count: stats.value.updates },
]);

const runAction = async (action, plugin) => {
  if (!plugin || busyFile.value) return;
  busyFile.value = plugin.file;
  error.value = '';
  notice.value = '';
  let redirecting = false;
  try {
    const data = await request(`/plugins/${action}`, {
      method: action === 'delete' ? 'DELETE' : 'POST',
      body: JSON.stringify({ file: plugin.file }),
    });
    notice.value = data.message || 'Done';
    if (data.redirect) {
      redirecting = true;
      window.location.href = data.redirect;
      return;
    }
  } catch (err) {
    error.value = err.message || 'Action failed';
  } finally {
    busyFile.value = '';
    // Always re-sync the list with the real backend state, even after an
    // error: activate_plugin() can return a WP_Error for benign "unexpected
    // output" from a plugin's own activation while still leaving the plugin
    // active, which previously left the row showing its stale pre-click
    // state (you clicked Activate but it still read "deactivated"). Skipped
    // only when navigating away (deactivating Aurora itself redirects).
    if (!redirecting) await load();
  }
};

const openAdd = () => {
  if (addUrl.value) {
    window.location.href = addUrl.value;
  }
};

const pluginInitial = (name) => (name || 'P').trim().charAt(0).toUpperCase();
const statusLabel = (plugin) => {
  if (plugin.networkActive) return 'Network active';
  return plugin.active ? 'Active' : 'Inactive';
};
const versionLine = (plugin) => {
  if (plugin.updateAvailable && plugin.newVersion) {
    return `${plugin.version || 'Unknown'} -> ${plugin.newVersion}`;
  }
  return plugin.version || 'Unknown';
};
</script>

<template>
  <main class="plugins-page">
    <header class="plugins-page__header">
      <div>
        <p class="plugins-page__eyebrow">Aurora Admin</p>
        <h1>Plugins</h1>
      </div>
      <div class="plugins-page__actions">
        <button
          type="button"
          class="icon-button"
          :class="{ 'is-active': compact }"
          title="Toggle compact view"
          @click="compact = !compact"
        >
          <span class="dashicons dashicons-menu-alt3" />
        </button>
        <button type="button" class="icon-button" title="Refresh plugins" :disabled="loading" @click="load">
          <span class="dashicons dashicons-update" />
        </button>
        <button type="button" class="primary-button" @click="openAdd">
          <span class="dashicons dashicons-plus-alt2" />
          Add New
        </button>
      </div>
    </header>

    <section class="plugins-page__toolbar" aria-label="Plugin filters and search">
      <div class="plugins-page__filters">
        <button
          v-for="filter in filters"
          :key="filter.key"
          type="button"
          class="filter-pill"
          :class="{ 'is-active': status === filter.key }"
          @click="status = filter.key"
        >
          <span>{{ filter.label }}</span>
          <strong>{{ filter.count }}</strong>
        </button>
      </div>
      <label class="plugins-page__search">
        <span class="dashicons dashicons-search" />
        <input v-model="search" type="search" placeholder="Search plugins..." />
      </label>
    </section>

    <div v-if="notice" class="plugins-notice plugins-notice--success">{{ notice }}</div>
    <div v-if="error" class="plugins-notice plugins-notice--error">{{ error }}</div>

    <section class="plugins-table" :class="{ 'is-compact': compact }" aria-label="Installed plugins">
      <div class="plugins-table__head">
        <div>Plugin Info</div>
        <div>Version <span class="dashicons dashicons-filter" /></div>
        <div>Status <span class="dashicons dashicons-filter" /></div>
        <div>Author <span class="dashicons dashicons-filter" /></div>
        <div>Actions</div>
      </div>

      <div v-if="loading" class="plugins-table__empty">Loading plugins...</div>
      <div v-else-if="!filtered.length" class="plugins-table__empty">No plugins match your filters.</div>

      <article
        v-for="plugin in filtered"
        v-else
        :key="plugin.file"
        class="plugin-row"
        :class="{ 'is-active': plugin.active }"
      >
        <div class="plugin-row__info">
          <div class="plugin-row__mark">
            <span>{{ pluginInitial(plugin.name) }}</span>
          </div>
          <div class="plugin-row__copy">
            <h2>{{ plugin.name }}</h2>
            <p>{{ plugin.description || 'No description provided.' }}</p>
            <div class="plugin-row__links">
              <a v-if="plugin.pluginUri" :href="plugin.pluginUri" target="_blank" rel="noopener noreferrer">
                Plugin site
              </a>
              <a v-if="plugin.authorUri" :href="plugin.authorUri" target="_blank" rel="noopener noreferrer">
                Author site
              </a>
              <span>{{ plugin.file }}</span>
            </div>
          </div>
        </div>

        <div class="plugin-row__cell" data-label="Version">
          <strong>{{ versionLine(plugin) }}</strong>
          <span v-if="plugin.updateAvailable">Update ready</span>
        </div>

        <div class="plugin-row__cell" data-label="Status">
          <span class="status-badge" :class="{ 'status-badge--active': plugin.active }">
            <i />
            {{ statusLabel(plugin) }}
          </span>
        </div>

        <div class="plugin-row__cell" data-label="Author">
          <strong>{{ plugin.author || 'Unknown' }}</strong>
        </div>

        <div class="plugin-row__actions">
          <button
            v-if="!plugin.active"
            type="button"
            class="toggle-button"
            title="Activate plugin"
            :disabled="busyFile === plugin.file || !plugin.canActivate"
            @click="runAction('activate', plugin)"
          >
            <span />
          </button>
          <button
            v-else
            type="button"
            class="toggle-button is-on"
            title="Deactivate plugin"
            :disabled="busyFile === plugin.file"
            @click="runAction('deactivate', plugin)"
          >
            <span />
          </button>
          <button
            v-if="plugin.updateAvailable"
            type="button"
            class="icon-button icon-button--inline"
            title="Update plugin"
            :disabled="busyFile === plugin.file || !plugin.canUpdate"
            @click="runAction('update', plugin)"
          >
            <span class="dashicons dashicons-update-alt" />
          </button>
          <button
            type="button"
            class="icon-button icon-button--inline icon-button--danger"
            title="Delete plugin"
            :disabled="busyFile === plugin.file || !plugin.canDelete"
            @click="runAction('delete', plugin)"
          >
            <span class="dashicons dashicons-trash" />
          </button>
          <a
            v-if="plugin.pluginUri"
            class="icon-button icon-button--inline"
            title="Open plugin site"
            :href="plugin.pluginUri"
            target="_blank"
            rel="noopener noreferrer"
          >
            <span class="dashicons dashicons-ellipsis" />
          </a>
        </div>
      </article>
    </section>
  </main>
</template>

<style scoped>
.plugins-page {
  min-height: calc(100vh - 112px);
  padding: 0;
  color: var(--aurora-text);
  background: transparent;
}
.plugins-page__header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 24px;
}
.plugins-page__eyebrow {
  margin: 0 0 7px;
  color: var(--aurora-text-muted);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0;
  text-transform: uppercase;
}
.plugins-page h1 {
  margin: 0;
  color: var(--aurora-text);
  font-size: 1.45rem;
  line-height: 1.15;
}
.plugins-page__actions,
.plugins-page__toolbar,
.plugins-page__filters,
.plugin-row__actions,
.plugin-row__links {
  display: flex;
  align-items: center;
}
.plugins-page__actions {
  gap: 10px;
}
.plugins-page__toolbar {
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 12px;
}
.plugins-page__filters {
  flex-wrap: wrap;
  gap: 8px;
}
.filter-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 34px;
  border: 1px solid var(--aurora-border);
  border-radius: 999px;
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text-muted);
  padding: 0 12px;
  font-size: 0.78rem;
  font-weight: 800;
  cursor: pointer;
}
.filter-pill strong {
  min-width: 22px;
  height: 22px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--aurora-frame-bg);
  color: inherit;
  font-size: 0.72rem;
}
.filter-pill.is-active {
  border-color: var(--aurora-text-muted);
  background: var(--aurora-frame-bg);
  color: var(--aurora-text);
}
.filter-pill.is-active strong {
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
}
.plugins-page__search {
  width: min(320px, 100%);
  min-height: 38px;
  border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-bg-subtle);
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 12px;
  color: var(--aurora-text-muted);
}
.plugins-page__search input {
  width: 100%;
  min-width: 0;
  border: 0;
  outline: 0;
  box-shadow: none;
  background: transparent;
  color: var(--aurora-text);
  font-size: 0.84rem;
}
.plugins-page__search input::placeholder {
  color: var(--aurora-text-muted);
}
.primary-button,
.icon-button {
  border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-sm);
  min-height: 42px;
  color: var(--aurora-text);
  background: var(--aurora-bg-subtle);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.primary-button {
  gap: 8px;
  border-color: var(--aurora-text-muted);
  background: var(--aurora-frame-bg);
  color: var(--aurora-text);
  padding: 0 16px;
  font-size: 0.84rem;
  font-weight: 850;
}
.icon-button {
  width: 42px;
  padding: 0;
  text-decoration: none;
}
.icon-button.is-active {
  border-color: var(--aurora-text-muted);
  color: var(--aurora-text);
}
.icon-button--inline {
  width: 30px;
  min-height: 30px;
  border: 0;
  background: transparent;
  color: var(--aurora-text-muted);
}
.icon-button:hover:not(:disabled),
.icon-button--inline:hover:not(:disabled) {
  color: var(--aurora-text);
  border-color: var(--aurora-text-muted);
}
.icon-button--danger:hover:not(:disabled) {
  color: var(--aurora-text);
}
.primary-button:disabled,
.icon-button:disabled,
.toggle-button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.plugins-notice {
  margin: 0 0 12px;
  padding: 10px 12px;
  border-radius: var(--aurora-radius-sm);
  font-size: 0.78rem;
  font-weight: 750;
}
.plugins-notice--success {
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
}
.plugins-notice--error {
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
}
.plugins-table {
  overflow: hidden;
  border: 1px solid var(--aurora-frame-border);
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-frame-bg);
  box-shadow: none;
}
.plugins-table__head,
.plugin-row {
  display: grid;
  grid-template-columns: minmax(320px, 1fr) 140px 140px 170px 150px;
  align-items: center;
}
.plugins-table__head {
  min-height: 48px;
  border-bottom: 1px solid var(--aurora-border);
  color: var(--aurora-text);
  font-size: 0.79rem;
  font-weight: 850;
}
.plugins-table__head > * {
  padding: 0 16px;
}
.plugins-table__head > div {
  min-height: 48px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.plugin-row {
  min-height: 78px;
  border-bottom: 1px solid var(--aurora-border);
  transition: background 160ms ease, border-color 160ms ease;
}
.plugin-row:last-child {
  border-bottom: 0;
}
.plugin-row:hover {
  background: var(--aurora-bg-subtle);
}
.plugin-row.is-active {
  background: var(--aurora-bg-subtle);
}
.plugin-row__info {
  display: grid;
  grid-template-columns: 46px minmax(0, 1fr);
  gap: 16px;
  align-items: center;
  min-width: 0;
  padding: 16px;
}
.plugin-row__mark {
  width: 46px;
  height: 46px;
  border-radius: var(--aurora-radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
  font-size: 1rem;
  font-weight: 900;
}
.plugin-row__copy {
  min-width: 0;
}
.plugin-row__copy h2 {
  margin: 0 0 5px;
  color: var(--aurora-text);
  font-size: 0.95rem;
  line-height: 1.2;
}
.plugin-row__copy p {
  margin: 0;
  color: var(--aurora-text-muted);
  font-size: 0.78rem;
  line-height: 1.35;
}
.plugin-row__links {
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 8px;
  color: var(--aurora-text-muted);
  font-size: 0.7rem;
}
.plugin-row__links a {
  color: var(--aurora-text);
  font-weight: 800;
  text-decoration: none;
}
.plugin-row__links span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.plugin-row__cell {
  min-width: 0;
  padding: 16px;
  color: var(--aurora-text-muted);
  font-size: 0.78rem;
}
.plugin-row__cell strong {
  display: block;
  overflow: hidden;
  color: var(--aurora-text-muted);
  font-size: 0.82rem;
  font-weight: 850;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.plugin-row__cell span {
  display: block;
  margin-top: 4px;
  color: var(--aurora-text-muted);
  font-size: 0.7rem;
  font-weight: 800;
}
.status-badge {
  width: fit-content;
  border-radius: 999px;
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text-muted);
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 10px;
  font-size: 0.75rem;
  font-weight: 850;
}
.status-badge i {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: currentColor;
  opacity: 0.8;
}
.status-badge--active {
  background: var(--aurora-frame-bg);
  color: var(--aurora-text);
  box-shadow: inset 0 0 0 1px var(--aurora-border);
}
.plugin-row__actions {
  gap: 8px;
  justify-content: flex-start;
  padding: 16px;
}
.plugins-table.is-compact .plugin-row {
  min-height: 62px;
}
.plugins-table.is-compact .plugin-row__info,
.plugins-table.is-compact .plugin-row__cell,
.plugins-table.is-compact .plugin-row__actions {
  padding-top: 10px;
  padding-bottom: 10px;
}
.plugins-table.is-compact .plugin-row__mark {
  width: 38px;
  height: 38px;
}
.plugins-table.is-compact .plugin-row__links {
  display: none;
}
.toggle-button {
  position: relative;
  width: 34px;
  height: 18px;
  border: 0;
  border-radius: 999px;
  background: var(--aurora-bg-subtle);
  cursor: pointer;
}
.toggle-button span {
  position: absolute;
  top: 3px;
  left: 3px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: var(--aurora-text-muted);
  transition: transform 160ms ease, background 160ms ease;
}
.toggle-button.is-on {
  background: var(--aurora-text-muted);
}
.toggle-button.is-on span {
  transform: translateX(16px);
  background: var(--aurora-frame-bg);
}
.plugins-table__empty {
  padding: 38px 18px;
  color: var(--aurora-text-muted);
  font-size: 0.85rem;
  text-align: center;
}

@media (max-width: 1120px) {
  .plugins-page {
    padding: 24px 18px 34px;
  }
  .plugins-table__head {
    display: none;
  }
  .plugin-row {
    grid-template-columns: 1fr;
    align-items: stretch;
    padding: 8px 0;
  }
  .plugin-row__cell,
  .plugin-row__actions {
    padding: 8px 16px;
  }
  .plugin-row__cell {
    display: flex;
    justify-content: space-between;
    gap: 16px;
  }
  .plugin-row__cell::before {
    content: attr(data-label);
    color: var(--aurora-text-muted);
    font-weight: 800;
  }
}

@media (max-width: 720px) {
  .plugins-page__header,
  .plugins-page__toolbar {
    align-items: stretch;
    flex-direction: column;
  }
  .plugins-page__actions {
    justify-content: flex-start;
  }
  .plugins-page__search {
    width: 100%;
  }
}
</style>
