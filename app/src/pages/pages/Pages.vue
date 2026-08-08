<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const pages = ref([]);
const stats = ref({ all: 0, publish: 0, draft: 0, pending: 0, trash: 0 });
const pagination = ref({ page: 1, perPage: 20, total: 0, totalPages: 1 });
const loading = ref(true);
const busyId = ref(0);
const error = ref('');
const notice = ref('');
const search = ref('');
const status = ref('all');

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
    const params = new URLSearchParams({
      status: status.value,
      search: search.value.trim(),
      page: String(pagination.value.page),
      perPage: String(pagination.value.perPage),
    });
    const data = await request(`/pages?${params.toString()}`);
    pages.value = data.items || [];
    stats.value = data.stats || stats.value;
    pagination.value = data.pagination || pagination.value;
  } catch (err) {
    error.value = err.message || 'Could not load pages';
  } finally {
    loading.value = false;
  }
};

onMounted(load);

let searchTimer = 0;
watch(search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    pagination.value.page = 1;
    load();
  }, 250);
});

const setStatus = (next) => {
  status.value = next;
  pagination.value.page = 1;
  load();
};

const filters = computed(() => [
  { key: 'all', label: 'All', count: stats.value.all },
  { key: 'publish', label: 'Published', count: stats.value.publish },
  { key: 'draft', label: 'Draft', count: stats.value.draft },
  { key: 'pending', label: 'Pending', count: stats.value.pending },
  { key: 'trash', label: 'Trash', count: stats.value.trash },
]);

const trashPage = async (item) => {
  if (!item || busyId.value) return;
  busyId.value = item.id;
  error.value = '';
  notice.value = '';
  try {
    const data = await request('/pages/trash', {
      method: 'POST',
      body: JSON.stringify({ id: item.id }),
    });
    notice.value = data.message || 'Page moved to trash.';
    await load();
  } catch (err) {
    error.value = err.message || 'Action failed';
  } finally {
    busyId.value = 0;
  }
};

const restorePage = async (item) => {
  if (!item || busyId.value) return;
  busyId.value = item.id;
  error.value = '';
  notice.value = '';
  try {
    const data = await request('/pages/restore', {
      method: 'POST',
      body: JSON.stringify({ id: item.id }),
    });
    notice.value = data.message || 'Page restored.';
    await load();
  } catch (err) {
    error.value = err.message || 'Action failed';
  } finally {
    busyId.value = 0;
  }
};

const deletePage = async (item) => {
  if (!item || busyId.value) return;
  if (!window.confirm(`Permanently delete "${item.title}"? This cannot be undone.`)) return;
  busyId.value = item.id;
  error.value = '';
  notice.value = '';
  try {
    const data = await request(`/pages/${item.id}`, { method: 'DELETE' });
    notice.value = data.message || 'Page deleted.';
    await load();
  } catch (err) {
    error.value = err.message || 'Action failed';
  } finally {
    busyId.value = 0;
  }
};

const goPage = (page) => {
  const next = Math.min(Math.max(1, page), pagination.value.totalPages || 1);
  if (next === pagination.value.page) return;
  pagination.value.page = next;
  load();
};

const rowRange = computed(() => {
  if (!pagination.value.total) return '0 to 0';
  const start = (pagination.value.page - 1) * pagination.value.perPage + 1;
  const end = Math.min(pagination.value.total, pagination.value.page * pagination.value.perPage);
  return `${start} to ${end}`;
});
</script>

<template>
  <main class="pages-page">
    <header class="pages-page__header">
      <div>
        <p class="pages-page__eyebrow">Content</p>
        <h1>Pages</h1>
      </div>
      <div class="pages-page__actions">
        <a class="button-primary" :href="serverData.newPageUrl">Add Page</a>
        <button type="button" class="icon-button" title="Refresh pages" :disabled="loading" @click="load">
          <span class="dashicons dashicons-update" />
        </button>
      </div>
    </header>

    <section class="pages-page__toolbar" aria-label="Page filters and search">
      <div class="pages-page__filters">
        <button
          v-for="filter in filters"
          :key="filter.key"
          type="button"
          class="filter-pill"
          :class="{ 'is-active': status === filter.key }"
          @click="setStatus(filter.key)"
        >
          <span>{{ filter.label }}</span>
          <strong>{{ filter.count }}</strong>
        </button>
      </div>
      <label class="pages-page__search">
        <span class="dashicons dashicons-search" />
        <input v-model="search" type="search" placeholder="Search pages..." />
      </label>
    </section>

    <div v-if="notice" class="pages-notice pages-notice--success">{{ notice }}</div>
    <div v-if="error" class="pages-notice pages-notice--error">{{ error }}</div>

    <section class="pages-table" aria-label="Pages">
      <div class="pages-table__head">
        <div>Title</div>
        <div>Author</div>
        <div>Parent</div>
        <div>Comments</div>
        <div>Date</div>
        <div>Actions</div>
      </div>

      <div v-if="loading" class="pages-table__empty">Loading pages...</div>
      <div v-else-if="!pages.length" class="pages-table__empty">No pages match your filters.</div>

      <article v-for="item in pages" v-else :key="item.id" class="page-row">
        <div class="page-row__title" data-label="Title">
          <a :href="item.editUrl">{{ item.title }}</a>
          <span class="status-badge" :class="`status-badge--${item.status}`">{{ item.statusLabel }}</span>
        </div>

        <div class="page-row__cell" data-label="Author">{{ item.author }}</div>

        <div class="page-row__cell" data-label="Parent">{{ item.parentTitle || '—' }}</div>

        <div class="page-row__cell" data-label="Comments">{{ item.commentCount }}</div>

        <div class="page-row__cell" data-label="Date">
          <strong>{{ item.dateLabel }}</strong>
          <span>{{ item.date }}</span>
        </div>

        <div class="page-row__actions">
          <a class="icon-button icon-button--inline" title="Edit page" :href="item.editUrl">
            <span class="dashicons dashicons-edit" />
          </a>
          <a
            v-if="item.viewUrl"
            class="icon-button icon-button--inline"
            title="View page"
            :href="item.viewUrl"
            target="_blank"
            rel="noopener"
          >
            <span class="dashicons dashicons-visibility" />
          </a>
          <button
            v-if="item.status !== 'trash'"
            type="button"
            class="icon-button icon-button--inline"
            title="Move to trash"
            :disabled="busyId === item.id"
            @click="trashPage(item)"
          >
            <span class="dashicons dashicons-trash" />
          </button>
          <template v-else>
            <button
              type="button"
              class="icon-button icon-button--inline"
              title="Restore"
              :disabled="busyId === item.id"
              @click="restorePage(item)"
            >
              <span class="dashicons dashicons-undo" />
            </button>
            <button
              v-if="item.canDelete"
              type="button"
              class="icon-button icon-button--inline"
              title="Delete permanently"
              :disabled="busyId === item.id"
              @click="deletePage(item)"
            >
              <span class="dashicons dashicons-no-alt" />
            </button>
          </template>
        </div>
      </article>

      <footer v-if="!loading && pages.length" class="pages-table__footer">
        <div>Page Size: {{ pagination.perPage }}</div>
        <div>{{ rowRange }} of {{ pagination.total }}</div>
        <div class="pages-table__pager">
          <button type="button" :disabled="pagination.page <= 1" @click="goPage(1)">
            <span class="dashicons dashicons-controls-skipback" />
          </button>
          <button type="button" :disabled="pagination.page <= 1" @click="goPage(pagination.page - 1)">
            <span class="dashicons dashicons-arrow-left-alt2" />
          </button>
          <span>Page {{ pagination.page }} of {{ pagination.totalPages }}</span>
          <button
            type="button"
            :disabled="pagination.page >= pagination.totalPages"
            @click="goPage(pagination.page + 1)"
          >
            <span class="dashicons dashicons-arrow-right-alt2" />
          </button>
          <button
            type="button"
            :disabled="pagination.page >= pagination.totalPages"
            @click="goPage(pagination.totalPages)"
          >
            <span class="dashicons dashicons-controls-skipforward" />
          </button>
        </div>
      </footer>
    </section>
  </main>
</template>

<style scoped>
.pages-page {
  min-height: calc(100vh - 112px);
  padding: 0;
  color: var(--aurora-text);
  background: transparent;
}
.pages-page__header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 24px;
}
.pages-page__eyebrow {
  margin: 0 0 7px;
  color: var(--aurora-text-muted);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0;
  text-transform: uppercase;
}
.pages-page h1 {
  margin: 0;
  color: var(--aurora-text);
  font-size: 1.45rem;
  line-height: 1.15;
}
.pages-page__actions,
.pages-page__toolbar,
.pages-page__filters,
.page-row__actions {
  display: flex;
  align-items: center;
}
.pages-page__actions {
  gap: 10px;
}
.button-primary {
  display: inline-flex;
  align-items: center;
  min-height: 38px;
  padding: 0 16px;
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-accent);
  color: var(--aurora-accent-text);
  font-size: 0.84rem;
  font-weight: 700;
  text-decoration: none;
}
.pages-page__toolbar {
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 12px;
}
.pages-page__filters {
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
.pages-page__search {
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
.pages-page__search input {
  width: 100%;
  min-width: 0;
  border: 0;
  outline: 0;
  box-shadow: none;
  background: transparent;
  color: var(--aurora-text);
  font-size: 0.84rem;
}
.pages-page__search input::placeholder {
  color: var(--aurora-text-muted);
}
.icon-button {
  width: 42px;
  min-height: 42px;
  border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-sm);
  color: var(--aurora-text);
  background: var(--aurora-bg-subtle);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  text-decoration: none;
}
.icon-button:hover:not(:disabled) {
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
.icon-button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.pages-notice {
  margin: 0 0 12px;
  padding: 10px 12px;
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
  font-size: 0.78rem;
  font-weight: 750;
}
.pages-table {
  overflow: hidden;
  border: 1px solid var(--aurora-frame-border);
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-frame-bg);
}
.pages-table__head,
.page-row {
  display: grid;
  grid-template-columns: minmax(220px, 1.6fr) minmax(120px, 0.8fr) minmax(160px, 1fr) 110px 130px 132px;
  align-items: center;
}
.pages-table__head {
  min-height: 48px;
  border-bottom: 1px solid var(--aurora-border);
  color: var(--aurora-text);
  font-size: 0.79rem;
  font-weight: 850;
}
.pages-table__head > div {
  min-height: 48px;
  padding: 0 16px;
  display: flex;
  align-items: center;
}
.page-row {
  min-height: 66px;
  border-bottom: 1px solid var(--aurora-border);
}
.page-row:hover {
  background: var(--aurora-bg-subtle);
}
.page-row__title {
  min-width: 0;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.page-row__title a {
  overflow: hidden;
  color: var(--aurora-text);
  font-weight: 800;
  font-size: 0.86rem;
  text-decoration: none;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.page-row__title a:hover {
  text-decoration: underline;
}
.page-row__cell {
  min-width: 0;
  padding: 14px 16px;
  color: var(--aurora-text-muted);
  font-size: 0.8rem;
  line-height: 1.45;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.page-row__cell span {
  display: block;
  color: var(--aurora-text-muted);
  font-size: 0.74rem;
}
.page-row__cell strong {
  display: block;
  color: var(--aurora-text-muted);
  font-size: 0.8rem;
}
.status-badge {
  width: fit-content;
  border-radius: 999px;
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text-muted);
  padding: 2px 9px;
  font-size: 0.68rem;
  font-weight: 850;
  box-shadow: inset 0 0 0 1px var(--aurora-border);
}
.page-row__actions {
  gap: 8px;
  padding: 14px 16px;
}
.pages-table__empty {
  padding: 38px 18px;
  color: var(--aurora-text-muted);
  font-size: 0.85rem;
  text-align: center;
}
.pages-table__footer {
  min-height: 54px;
  border-top: 1px solid var(--aurora-border);
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 26px;
  padding: 0 16px;
  color: var(--aurora-text-muted);
  font-size: 0.8rem;
}
.pages-table__pager {
  display: flex;
  align-items: center;
  gap: 8px;
}
.pages-table__pager button {
  width: 28px;
  height: 28px;
  border: 0;
  border-radius: var(--aurora-radius-sm);
  background: transparent;
  color: var(--aurora-text-muted);
  cursor: pointer;
}
.pages-table__pager button:not(:disabled):hover {
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
}
.pages-table__pager button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

@media (max-width: 1280px) {
  .pages-table__head,
  .page-row {
    grid-template-columns: minmax(200px, 1.6fr) minmax(100px, 0.8fr) minmax(140px, 1fr) 100px 120px 122px;
  }
}

@media (max-width: 1080px) {
  .pages-table__head {
    display: none;
  }
  .page-row {
    grid-template-columns: 1fr;
    align-items: stretch;
    padding: 8px 0;
  }
  .page-row__title,
  .page-row__cell,
  .page-row__actions {
    padding: 8px 16px;
  }
  .page-row__cell {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    white-space: normal;
  }
  .page-row__cell::before {
    content: attr(data-label);
    color: var(--aurora-text-muted);
    font-weight: 800;
  }
  .pages-table__footer,
  .pages-page__header,
  .pages-page__toolbar {
    align-items: stretch;
    flex-direction: column;
  }
  .pages-table__footer {
    padding: 14px 16px;
    gap: 10px;
  }
  .pages-page__search {
    width: 100%;
  }
}
</style>
