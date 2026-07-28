<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const users = ref([]);
const stats = ref({ all: 0, byRole: {} });
const pagination = ref({ page: 1, perPage: 20, total: 0, totalPages: 1 });
const loading = ref(true);
const busyId = ref(0);
const error = ref('');
const notice = ref('');
const search = ref('');
const role = ref('all');

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
      role: role.value,
      search: search.value.trim(),
      page: String(pagination.value.page),
      perPage: String(pagination.value.perPage),
    });
    const data = await request(`/users?${params.toString()}`);
    users.value = data.items || [];
    stats.value = data.stats || stats.value;
    pagination.value = data.pagination || pagination.value;
  } catch (err) {
    error.value = err.message || 'Could not load users';
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

const setRole = (next) => {
  role.value = next;
  pagination.value.page = 1;
  load();
};

const filters = computed(() => {
  const roleFilters = Object.entries(stats.value.byRole || {}).map(([key, count]) => ({
    key,
    label: key.charAt(0).toUpperCase() + key.slice(1),
    count,
  }));
  return [{ key: 'all', label: 'All', count: stats.value.all }, ...roleFilters];
});

const deleteUser = async (user) => {
  if (!user || busyId.value) return;
  if (!window.confirm(`Delete "${user.displayName}"? Their content will be reassigned to you. This cannot be undone.`)) return;
  busyId.value = user.id;
  error.value = '';
  notice.value = '';
  try {
    const data = await request(`/users/${user.id}`, { method: 'DELETE' });
    notice.value = data.message || 'User deleted.';
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

const initials = (name) =>
  (name || '?')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');
</script>

<template>
  <main class="users-page">
    <header class="users-page__header">
      <div>
        <p class="users-page__eyebrow">Accounts</p>
        <h1>Users</h1>
      </div>
      <div class="users-page__actions">
        <a class="button-primary" :href="serverData.newUserUrl">Add User</a>
        <button type="button" class="icon-button" title="Refresh users" :disabled="loading" @click="load">
          <span class="dashicons dashicons-update" />
        </button>
      </div>
    </header>

    <section class="users-page__toolbar" aria-label="User filters and search">
      <div class="users-page__filters">
        <button
          v-for="filter in filters"
          :key="filter.key"
          type="button"
          class="filter-pill"
          :class="{ 'is-active': role === filter.key }"
          @click="setRole(filter.key)"
        >
          <span>{{ filter.label }}</span>
          <strong>{{ filter.count }}</strong>
        </button>
      </div>
      <label class="users-page__search">
        <span class="dashicons dashicons-search" />
        <input v-model="search" type="search" placeholder="Search users..." />
      </label>
    </section>

    <div v-if="notice" class="users-notice users-notice--success">{{ notice }}</div>
    <div v-if="error" class="users-notice users-notice--error">{{ error }}</div>

    <section class="users-table" aria-label="Users">
      <div class="users-table__head">
        <div>User</div>
        <div>Email</div>
        <div>Role</div>
        <div>Posts</div>
        <div>Registered</div>
        <div>Actions</div>
      </div>

      <div v-if="loading" class="users-table__empty">Loading users...</div>
      <div v-else-if="!users.length" class="users-table__empty">No users match your filters.</div>

      <article v-for="user in users" v-else :key="user.id" class="user-row">
        <div class="user-row__user">
          <img v-if="user.avatarUrl" :src="user.avatarUrl" alt="" class="user-row__avatar" />
          <div v-else class="user-row__initials">{{ initials(user.displayName) }}</div>
          <div class="user-row__person">
            <strong>{{ user.displayName }}</strong>
            <span>{{ user.username }}</span>
          </div>
        </div>

        <div class="user-row__cell" data-label="Email">{{ user.email }}</div>

        <div class="user-row__cell" data-label="Role">{{ user.roles.join(', ') || '—' }}</div>

        <div class="user-row__cell" data-label="Posts">{{ user.postCount }}</div>

        <div class="user-row__cell" data-label="Registered">{{ user.registeredDate }}</div>

        <div class="user-row__actions">
          <a class="icon-button icon-button--inline" title="Edit user" :href="user.editUrl">
            <span class="dashicons dashicons-edit" />
          </a>
          <button
            v-if="user.canDelete"
            type="button"
            class="icon-button icon-button--inline"
            title="Delete user"
            :disabled="busyId === user.id"
            @click="deleteUser(user)"
          >
            <span class="dashicons dashicons-trash" />
          </button>
        </div>
      </article>

      <footer v-if="!loading && users.length" class="users-table__footer">
        <div>Page Size: {{ pagination.perPage }}</div>
        <div>{{ rowRange }} of {{ pagination.total }}</div>
        <div class="users-table__pager">
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
.users-page {
  min-height: calc(100vh - 112px);
  padding: 0;
  color: var(--aurora-text);
  background: transparent;
}
.users-page__header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 24px;
}
.users-page__eyebrow {
  margin: 0 0 7px;
  color: var(--aurora-text-muted);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0;
  text-transform: uppercase;
}
.users-page h1 {
  margin: 0;
  color: var(--aurora-text);
  font-size: 1.45rem;
  line-height: 1.15;
}
.users-page__actions,
.users-page__toolbar,
.users-page__filters,
.user-row__actions {
  display: flex;
  align-items: center;
}
.users-page__actions {
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
.users-page__toolbar {
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 12px;
}
.users-page__filters {
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
.users-page__search {
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
.users-page__search input {
  width: 100%;
  min-width: 0;
  border: 0;
  outline: 0;
  box-shadow: none;
  background: transparent;
  color: var(--aurora-text);
  font-size: 0.84rem;
}
.users-page__search input::placeholder {
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
.users-notice {
  margin: 0 0 12px;
  padding: 10px 12px;
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
  font-size: 0.78rem;
  font-weight: 750;
}
.users-table {
  overflow: hidden;
  border: 1px solid var(--aurora-frame-border);
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-frame-bg);
}
.users-table__head,
.user-row {
  display: grid;
  grid-template-columns: minmax(200px, 1.4fr) minmax(180px, 1.2fr) minmax(110px, 0.8fr) 90px 130px 100px;
  align-items: center;
}
.users-table__head {
  min-height: 48px;
  border-bottom: 1px solid var(--aurora-border);
  color: var(--aurora-text);
  font-size: 0.79rem;
  font-weight: 850;
}
.users-table__head > div {
  min-height: 48px;
  padding: 0 16px;
  display: flex;
  align-items: center;
}
.user-row {
  min-height: 66px;
  border-bottom: 1px solid var(--aurora-border);
}
.user-row:hover {
  background: var(--aurora-bg-subtle);
}
.user-row__user {
  display: grid;
  grid-template-columns: 36px minmax(0, 1fr);
  gap: 12px;
  align-items: center;
  min-width: 0;
  padding: 14px 16px;
}
.user-row__avatar,
.user-row__initials {
  width: 36px;
  height: 36px;
  border-radius: 50%;
}
.user-row__avatar {
  display: block;
}
.user-row__initials {
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.72rem;
  font-weight: 900;
}
.user-row__person {
  min-width: 0;
}
.user-row__person strong {
  display: block;
  overflow: hidden;
  color: var(--aurora-text);
  font-size: 0.86rem;
  line-height: 1.2;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.user-row__person span {
  display: block;
  overflow: hidden;
  color: var(--aurora-text-muted);
  font-size: 0.74rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.user-row__cell {
  min-width: 0;
  padding: 14px 16px;
  color: var(--aurora-text-muted);
  font-size: 0.8rem;
  line-height: 1.45;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.user-row__actions {
  gap: 8px;
  padding: 14px 16px;
}
.users-table__empty {
  padding: 38px 18px;
  color: var(--aurora-text-muted);
  font-size: 0.85rem;
  text-align: center;
}
.users-table__footer {
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
.users-table__pager {
  display: flex;
  align-items: center;
  gap: 8px;
}
.users-table__pager button {
  width: 28px;
  height: 28px;
  border: 0;
  border-radius: var(--aurora-radius-sm);
  background: transparent;
  color: var(--aurora-text-muted);
  cursor: pointer;
}
.users-table__pager button:not(:disabled):hover {
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
}
.users-table__pager button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

@media (max-width: 1280px) {
  .users-table__head,
  .user-row {
    grid-template-columns: minmax(180px, 1.4fr) minmax(160px, 1.2fr) minmax(100px, 0.8fr) 80px 120px 90px;
  }
}

@media (max-width: 1080px) {
  .users-table__head {
    display: none;
  }
  .user-row {
    grid-template-columns: 1fr;
    align-items: stretch;
    padding: 8px 0;
  }
  .user-row__cell,
  .user-row__actions {
    padding: 8px 16px;
  }
  .user-row__cell {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    white-space: normal;
  }
  .user-row__cell::before {
    content: attr(data-label);
    color: var(--aurora-text-muted);
    font-weight: 800;
  }
  .users-table__footer,
  .users-page__header,
  .users-page__toolbar {
    align-items: stretch;
    flex-direction: column;
  }
  .users-table__footer {
    padding: 14px 16px;
    gap: 10px;
  }
  .users-page__search {
    width: 100%;
  }
}
</style>
