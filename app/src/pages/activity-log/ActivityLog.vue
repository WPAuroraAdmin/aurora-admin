<script setup>
import { ref, computed, watch, onMounted } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const api = (path) =>
  fetch(`${props.serverData.restUrl}aurora-admin/v1${path}`, {
    headers: { 'X-WP-Nonce': props.serverData.restNonce },
  }).then((r) => {
    if (!r.ok) throw new Error('Request failed');
    return r.json();
  });

// Two tabs: "Admin" = site/content activity, "User" = account activity
// (login/logout/registration/profile updates) — object_type 'user' is the
// dividing line the REST route's `scope` param filters on.
const TABS = [
  { key: 'admin', label: 'Admin' },
  { key: 'user', label: 'User' },
];

const ADMIN_ACTIONS = [
  ['', 'All Actions'],
  ['created', 'Created'],
  ['updated', 'Updated'],
  ['deleted', 'Deleted'],
  ['activated', 'Activated'],
  ['deactivated', 'Deactivated'],
  ['switched', 'Switched'],
];
const USER_ACTIONS = [
  ['', 'All Actions'],
  ['registered', 'Registered'],
  ['profile_updated', 'Profile updated'],
  ['login', 'Logged in'],
  ['logout', 'Logged out'],
];
const OBJECT_TYPES = [
  ['', 'All Types'],
  ['post', 'Post'],
  ['page', 'Page'],
  ['attachment', 'Attachment'],
  ['plugin', 'Plugin'],
  ['theme', 'Theme'],
  ['settings', 'Settings'],
];

const TYPE_LABELS = {
  post: 'Post',
  page: 'Page',
  attachment: 'Attachment',
  user: 'User',
  plugin: 'Plugin',
  theme: 'Theme',
  settings: 'Settings',
};
const ACTION_LABELS = {
  created: 'Created',
  updated: 'Updated',
  deleted: 'Deleted',
  registered: 'Registered',
  profile_updated: 'Profile updated',
  login: 'Logged in',
  logout: 'Logged out',
  activated: 'Activated',
  deactivated: 'Deactivated',
  switched: 'Switched',
};
const typeLabel = (t) => TYPE_LABELS[t] || (t ? t.charAt(0).toUpperCase() + t.slice(1) : '—');
const actionLabel = (a) => ACTION_LABELS[a] || a;

const scope = ref('admin');
const actionOptions = computed(() => (scope.value === 'user' ? USER_ACTIONS : ADMIN_ACTIONS));

const items = ref([]);
const loading = ref(true);
const total = ref(0);
const page = ref(1);
const totalPages = ref(1);
const actionFilter = ref('');
const objectFilter = ref('');
const search = ref('');

const load = async () => {
  loading.value = true;
  try {
    const params = new URLSearchParams({ scope: scope.value });
    if (actionFilter.value) params.set('action_type', actionFilter.value);
    if (scope.value === 'admin' && objectFilter.value) params.set('object_type', objectFilter.value);
    if (search.value.trim()) params.set('search', search.value.trim());
    params.set('page', page.value);
    const data = await api(`/activity-log?${params.toString()}`);
    items.value = data.items;
    total.value = data.total;
    totalPages.value = data.totalPages;
  } finally {
    loading.value = false;
  }
};

onMounted(load);
watch([actionFilter, objectFilter, search], () => {
  page.value = 1;
  load();
});
watch(page, load);

const setScope = (key) => {
  if (scope.value === key) return;
  scope.value = key;
  actionFilter.value = '';
  objectFilter.value = '';
  page.value = 1;
  load();
};

const formatDate = (s) => (s ? new Date(s.replace(' ', 'T')).toLocaleString() : '');

const rowRange = computed(() => {
  if (!total.value) return '0';
  const start = (page.value - 1) * 20 + 1;
  const end = Math.min(page.value * 20, total.value);
  return `${start}–${end}`;
});
const goPage = (p) => {
  page.value = Math.min(Math.max(1, p), totalPages.value);
};
</script>

<template>
  <main class="activity-page">
    <header class="activity-page__header">
      <div>
        <p class="activity-page__eyebrow">Aurora Admin</p>
        <h1>Activity Log</h1>
      </div>
      <button type="button" class="icon-button" title="Refresh" :disabled="loading" @click="load">
        <span class="dashicons dashicons-update" />
      </button>
    </header>

    <div class="activity-page__tabs">
      <button
        v-for="tab in TABS"
        :key="tab.key"
        type="button"
        class="filter-pill"
        :class="{ 'is-active': scope === tab.key }"
        @click="setScope(tab.key)"
      >
        {{ tab.label }}
      </button>
    </div>

    <section class="activity-page__toolbar" aria-label="Activity filters and search">
      <div class="activity-page__filters">
        <select v-model="actionFilter" class="activity-page__select">
          <option v-for="[v, l] in actionOptions" :key="v" :value="v">{{ l }}</option>
        </select>
        <select v-if="scope === 'admin'" v-model="objectFilter" class="activity-page__select">
          <option v-for="[v, l] in OBJECT_TYPES" :key="v" :value="v">{{ l }}</option>
        </select>
      </div>
      <label class="activity-page__search">
        <span class="dashicons dashicons-search" />
        <input v-model="search" type="search" placeholder="Search activity…" />
      </label>
    </section>

    <section class="activity-table" aria-label="Activity Log">
      <div class="activity-table__head">
        <div>Date</div>
        <div>Author</div>
        <div>IP Address</div>
        <div>Type</div>
        <div>Action</div>
        <div>Description</div>
      </div>

      <div v-if="loading" class="activity-table__empty">Loading activity...</div>
      <div v-else-if="!items.length" class="activity-table__empty">No activity found.</div>

      <article v-for="i in items" v-else :key="i.id" class="activity-row">
        <div class="activity-row__cell" data-label="Date">{{ formatDate(i.createdAt) }}</div>
        <div class="activity-row__cell activity-row__author" data-label="Author">
          <template v-if="i.userId">
            <img class="activity-row__avatar" :src="i.userAvatar" :alt="i.userName" width="32" height="32" />
            <div class="activity-row__author-info">
              <a :href="i.userEditUrl">{{ i.userName }}</a>
              <span v-if="i.userRole" class="activity-row__role">{{ i.userRole }}</span>
              <span v-if="i.userEmail" class="activity-row__email">{{ i.userEmail }}</span>
            </div>
          </template>
          <span v-else>System</span>
        </div>
        <div class="activity-row__cell" data-label="IP Address">{{ i.ipAddress || '—' }}</div>
        <div class="activity-row__cell" data-label="Type">
          <span class="type-badge">{{ typeLabel(i.objectType) }}</span>
        </div>
        <div class="activity-row__cell" data-label="Action">{{ actionLabel(i.actionType) }}</div>
        <div class="activity-row__cell activity-row__desc" data-label="Description">{{ i.objectLabel || '—' }}</div>
      </article>

      <footer v-if="!loading && items.length" class="activity-table__footer">
        <div>{{ rowRange }} of {{ total }}</div>
        <div class="activity-table__pager">
          <button type="button" :disabled="page <= 1" @click="goPage(1)">
            <span class="dashicons dashicons-controls-skipback" />
          </button>
          <button type="button" :disabled="page <= 1" @click="goPage(page - 1)">
            <span class="dashicons dashicons-arrow-left-alt2" />
          </button>
          <span>Page {{ page }} of {{ totalPages }}</span>
          <button type="button" :disabled="page >= totalPages" @click="goPage(page + 1)">
            <span class="dashicons dashicons-arrow-right-alt2" />
          </button>
          <button type="button" :disabled="page >= totalPages" @click="goPage(totalPages)">
            <span class="dashicons dashicons-controls-skipforward" />
          </button>
        </div>
      </footer>
    </section>
  </main>
</template>

<style scoped>
.activity-page { min-height: calc(100vh - 112px); padding: 0; color: var(--aurora-text); background: transparent; }
.activity-page__header {
  display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; margin-bottom: 18px;
}
.activity-page__eyebrow {
  margin: 0 0 7px; color: var(--aurora-text-muted); font-size: 0.72rem;
  font-weight: 800; letter-spacing: 0; text-transform: uppercase;
}
.activity-page h1 { margin: 0; color: var(--aurora-text); font-size: 1.45rem; line-height: 1.15; }

.icon-button {
  width: 42px; min-height: 42px; border: 1px solid var(--aurora-border); border-radius: var(--aurora-radius-sm);
  color: var(--aurora-text); background: var(--aurora-bg-subtle); display: inline-flex;
  align-items: center; justify-content: center; cursor: pointer;
}
.icon-button:hover:not(:disabled) { border-color: var(--aurora-text-muted); }
.icon-button:disabled { opacity: 0.45; cursor: not-allowed; }

.activity-page__tabs { display: flex; gap: 8px; margin-bottom: 14px; }
.filter-pill {
  display: inline-flex; align-items: center; gap: 8px; min-height: 34px;
  border: 1px solid var(--aurora-border); border-radius: 999px; background: var(--aurora-bg-subtle);
  color: var(--aurora-text-muted); padding: 0 16px; font-size: 0.78rem; font-weight: 800; cursor: pointer;
}
.filter-pill.is-active { border-color: var(--aurora-text-muted); background: var(--aurora-frame-bg); color: var(--aurora-text); }

.activity-page__toolbar { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 12px; }
.activity-page__filters { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.activity-page__select {
  padding: 7px 10px; border-radius: var(--aurora-radius-sm); border: 1px solid var(--aurora-border);
  background: var(--aurora-bg-subtle); color: var(--aurora-text); font-size: 0.78rem;
}
.activity-page__search {
  width: min(280px, 100%); min-height: 38px; border: 1px solid var(--aurora-border); border-radius: var(--aurora-radius-sm);
  background: var(--aurora-bg-subtle); display: flex; align-items: center; gap: 8px; padding: 0 12px; color: var(--aurora-text-muted);
}
.activity-page__search input {
  width: 100%; min-width: 0; border: 0; outline: 0; box-shadow: none; background: transparent;
  color: var(--aurora-text); font-size: 0.84rem;
}
.activity-page__search input::placeholder { color: var(--aurora-text-muted); }

.activity-table { overflow: hidden; border: 1px solid var(--aurora-frame-border); border-radius: var(--aurora-radius-sm); background: var(--aurora-frame-bg); }
.activity-table__head, .activity-row {
  display: grid;
  grid-template-columns: 190px minmax(160px, 0.8fr) 130px 110px 140px minmax(180px, 1.6fr);
  align-items: center;
}
.activity-table__head {
  min-height: 44px; border-bottom: 1px solid var(--aurora-border); color: var(--aurora-text);
  font-size: 0.79rem; font-weight: 850;
}
.activity-table__head > div { min-height: 44px; padding: 0 16px; display: flex; align-items: center; }
.activity-row { min-height: 76px; border-bottom: 1px solid var(--aurora-border); }
.activity-row:hover { background: var(--aurora-bg-subtle); }
.activity-row__cell {
  min-width: 0; padding: 12px 16px; color: var(--aurora-text-muted); font-size: 0.8rem;
  line-height: 1.4; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.activity-row__desc { color: var(--aurora-text); white-space: normal; }

.activity-row__author { display: flex; align-items: center; gap: 10px; white-space: normal; }
.activity-row__avatar { border-radius: 50%; flex-shrink: 0; display: block; }
.activity-row__author-info { min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.activity-row__author-info a {
  color: var(--aurora-accent); font-weight: 700; font-size: 0.8125rem; text-decoration: none;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.activity-row__author-info a:hover { text-decoration: underline; }
.activity-row__role, .activity-row__email {
  font-size: 0.71rem; color: var(--aurora-text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.type-badge {
  display: inline-block; border-radius: 999px; background: var(--aurora-bg-subtle); color: var(--aurora-text-muted);
  padding: 2px 10px; font-size: 0.68rem; font-weight: 850; box-shadow: inset 0 0 0 1px var(--aurora-border);
}
.activity-table__empty { padding: 38px 18px; color: var(--aurora-text-muted); font-size: 0.85rem; text-align: center; }
.activity-table__footer {
  min-height: 50px; border-top: 1px solid var(--aurora-border); display: flex; align-items: center;
  justify-content: flex-end; gap: 26px; padding: 0 16px; color: var(--aurora-text-muted); font-size: 0.8rem;
}
.activity-table__pager { display: flex; align-items: center; gap: 8px; }
.activity-table__pager button {
  width: 28px; height: 28px; border: 0; border-radius: var(--aurora-radius-sm);
  background: transparent; color: var(--aurora-text-muted); cursor: pointer;
}
.activity-table__pager button:not(:disabled):hover { background: var(--aurora-bg-subtle); color: var(--aurora-text); }
.activity-table__pager button:disabled { opacity: 0.4; cursor: not-allowed; }

@media (max-width: 1100px) {
  .activity-table__head { display: none; }
  .activity-row { grid-template-columns: 1fr; padding: 10px 0; }
  .activity-row__cell { white-space: normal; }
  .activity-row__cell::before { content: attr(data-label) ': '; font-weight: 700; color: var(--aurora-text); }
}
</style>
