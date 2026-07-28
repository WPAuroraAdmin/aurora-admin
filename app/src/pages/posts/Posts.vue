<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const posts = ref([]);
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
    const data = await request(`/posts?${params.toString()}`);
    posts.value = data.items || [];
    stats.value = data.stats || stats.value;
    pagination.value = data.pagination || pagination.value;
  } catch (err) {
    error.value = err.message || 'Could not load posts';
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

const trashPost = async (post) => {
  if (!post || busyId.value) return;
  busyId.value = post.id;
  error.value = '';
  notice.value = '';
  try {
    const data = await request('/posts/trash', {
      method: 'POST',
      body: JSON.stringify({ id: post.id }),
    });
    notice.value = data.message || 'Post moved to trash.';
    await load();
  } catch (err) {
    error.value = err.message || 'Action failed';
  } finally {
    busyId.value = 0;
  }
};

const restorePost = async (post) => {
  if (!post || busyId.value) return;
  busyId.value = post.id;
  error.value = '';
  notice.value = '';
  try {
    const data = await request('/posts/restore', {
      method: 'POST',
      body: JSON.stringify({ id: post.id }),
    });
    notice.value = data.message || 'Post restored.';
    await load();
  } catch (err) {
    error.value = err.message || 'Action failed';
  } finally {
    busyId.value = 0;
  }
};

const deletePost = async (post) => {
  if (!post || busyId.value) return;
  if (!window.confirm(`Permanently delete "${post.title}"? This cannot be undone.`)) return;
  busyId.value = post.id;
  error.value = '';
  notice.value = '';
  try {
    const data = await request(`/posts/${post.id}`, { method: 'DELETE' });
    notice.value = data.message || 'Post deleted.';
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
  <main class="posts-page">
    <header class="posts-page__header">
      <div>
        <p class="posts-page__eyebrow">Content</p>
        <h1>Posts</h1>
      </div>
      <div class="posts-page__actions">
        <a class="button-primary" :href="serverData.newPostUrl">Add Post</a>
        <button type="button" class="icon-button" title="Refresh posts" :disabled="loading" @click="load">
          <span class="dashicons dashicons-update" />
        </button>
      </div>
    </header>

    <section class="posts-page__toolbar" aria-label="Post filters and search">
      <div class="posts-page__filters">
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
      <label class="posts-page__search">
        <span class="dashicons dashicons-search" />
        <input v-model="search" type="search" placeholder="Search posts..." />
      </label>
    </section>

    <div v-if="notice" class="posts-notice posts-notice--success">{{ notice }}</div>
    <div v-if="error" class="posts-notice posts-notice--error">{{ error }}</div>

    <section class="posts-table" aria-label="Posts">
      <div class="posts-table__head">
        <div>Title</div>
        <div>Author</div>
        <div>Categories</div>
        <div>Comments</div>
        <div>Date</div>
        <div>Actions</div>
      </div>

      <div v-if="loading" class="posts-table__empty">Loading posts...</div>
      <div v-else-if="!posts.length" class="posts-table__empty">No posts match your filters.</div>

      <article v-for="post in posts" v-else :key="post.id" class="post-row">
        <div class="post-row__title" data-label="Title">
          <a :href="post.editUrl">{{ post.title }}</a>
          <span class="status-badge" :class="`status-badge--${post.status}`">{{ post.statusLabel }}</span>
        </div>

        <div class="post-row__cell" data-label="Author">{{ post.author }}</div>

        <div class="post-row__cell" data-label="Categories">
          {{ post.categories.length ? post.categories.join(', ') : '—' }}
        </div>

        <div class="post-row__cell" data-label="Comments">{{ post.commentCount }}</div>

        <div class="post-row__cell" data-label="Date">
          <strong>{{ post.dateLabel }}</strong>
          <span>{{ post.date }}</span>
        </div>

        <div class="post-row__actions">
          <a class="icon-button icon-button--inline" title="Edit post" :href="post.editUrl">
            <span class="dashicons dashicons-edit" />
          </a>
          <a
            v-if="post.viewUrl"
            class="icon-button icon-button--inline"
            title="View post"
            :href="post.viewUrl"
            target="_blank"
            rel="noopener"
          >
            <span class="dashicons dashicons-visibility" />
          </a>
          <button
            v-if="post.status !== 'trash'"
            type="button"
            class="icon-button icon-button--inline"
            title="Move to trash"
            :disabled="busyId === post.id"
            @click="trashPost(post)"
          >
            <span class="dashicons dashicons-trash" />
          </button>
          <template v-else>
            <button
              type="button"
              class="icon-button icon-button--inline"
              title="Restore"
              :disabled="busyId === post.id"
              @click="restorePost(post)"
            >
              <span class="dashicons dashicons-undo" />
            </button>
            <button
              v-if="post.canDelete"
              type="button"
              class="icon-button icon-button--inline"
              title="Delete permanently"
              :disabled="busyId === post.id"
              @click="deletePost(post)"
            >
              <span class="dashicons dashicons-no-alt" />
            </button>
          </template>
        </div>
      </article>

      <footer v-if="!loading && posts.length" class="posts-table__footer">
        <div>Page Size: {{ pagination.perPage }}</div>
        <div>{{ rowRange }} of {{ pagination.total }}</div>
        <div class="posts-table__pager">
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
.posts-page {
  min-height: calc(100vh - 112px);
  padding: 0;
  color: var(--aurora-text);
  background: transparent;
}
.posts-page__header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 24px;
}
.posts-page__eyebrow {
  margin: 0 0 7px;
  color: var(--aurora-text-muted);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0;
  text-transform: uppercase;
}
.posts-page h1 {
  margin: 0;
  color: var(--aurora-text);
  font-size: 1.45rem;
  line-height: 1.15;
}
.posts-page__actions,
.posts-page__toolbar,
.posts-page__filters,
.post-row__actions {
  display: flex;
  align-items: center;
}
.posts-page__actions {
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
.posts-page__toolbar {
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 12px;
}
.posts-page__filters {
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
.posts-page__search {
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
.posts-page__search input {
  width: 100%;
  min-width: 0;
  border: 0;
  outline: 0;
  box-shadow: none;
  background: transparent;
  color: var(--aurora-text);
  font-size: 0.84rem;
}
.posts-page__search input::placeholder {
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
.posts-notice {
  margin: 0 0 12px;
  padding: 10px 12px;
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
  font-size: 0.78rem;
  font-weight: 750;
}
.posts-table {
  overflow: hidden;
  border: 1px solid var(--aurora-frame-border);
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-frame-bg);
}
.posts-table__head,
.post-row {
  display: grid;
  grid-template-columns: minmax(220px, 1.6fr) minmax(120px, 0.8fr) minmax(160px, 1fr) 110px 130px 132px;
  align-items: center;
}
.posts-table__head {
  min-height: 48px;
  border-bottom: 1px solid var(--aurora-border);
  color: var(--aurora-text);
  font-size: 0.79rem;
  font-weight: 850;
}
.posts-table__head > div {
  min-height: 48px;
  padding: 0 16px;
  display: flex;
  align-items: center;
}
.post-row {
  min-height: 66px;
  border-bottom: 1px solid var(--aurora-border);
}
.post-row:hover {
  background: var(--aurora-bg-subtle);
}
.post-row__title {
  min-width: 0;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.post-row__title a {
  overflow: hidden;
  color: var(--aurora-text);
  font-weight: 800;
  font-size: 0.86rem;
  text-decoration: none;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.post-row__title a:hover {
  text-decoration: underline;
}
.post-row__cell {
  min-width: 0;
  padding: 14px 16px;
  color: var(--aurora-text-muted);
  font-size: 0.8rem;
  line-height: 1.45;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.post-row__cell span {
  display: block;
  color: var(--aurora-text-muted);
  font-size: 0.74rem;
}
.post-row__cell strong {
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
.post-row__actions {
  gap: 8px;
  padding: 14px 16px;
}
.posts-table__empty {
  padding: 38px 18px;
  color: var(--aurora-text-muted);
  font-size: 0.85rem;
  text-align: center;
}
.posts-table__footer {
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
.posts-table__pager {
  display: flex;
  align-items: center;
  gap: 8px;
}
.posts-table__pager button {
  width: 28px;
  height: 28px;
  border: 0;
  border-radius: var(--aurora-radius-sm);
  background: transparent;
  color: var(--aurora-text-muted);
  cursor: pointer;
}
.posts-table__pager button:not(:disabled):hover {
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
}
.posts-table__pager button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

@media (max-width: 1280px) {
  .posts-table__head,
  .post-row {
    grid-template-columns: minmax(200px, 1.6fr) minmax(100px, 0.8fr) minmax(140px, 1fr) 100px 120px 122px;
  }
}

@media (max-width: 1080px) {
  .posts-table__head {
    display: none;
  }
  .post-row {
    grid-template-columns: 1fr;
    align-items: stretch;
    padding: 8px 0;
  }
  .post-row__title,
  .post-row__cell,
  .post-row__actions {
    padding: 8px 16px;
  }
  .post-row__cell {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    white-space: normal;
  }
  .post-row__cell::before {
    content: attr(data-label);
    color: var(--aurora-text-muted);
    font-weight: 800;
  }
  .posts-table__footer,
  .posts-page__header,
  .posts-page__toolbar {
    align-items: stretch;
    flex-direction: column;
  }
  .posts-table__footer {
    padding: 14px 16px;
    gap: 10px;
  }
  .posts-page__search {
    width: 100%;
  }
}
</style>
