<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const comments = ref([]);
const stats = ref({ all: 0, approved: 0, pending: 0, spam: 0, trash: 0 });
const pagination = ref({ page: 1, perPage: 50, total: 0, totalPages: 1 });
const loading = ref(true);
const busyId = ref(0);
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
    const params = new URLSearchParams({
      status: status.value,
      search: search.value.trim(),
      page: String(pagination.value.page),
      perPage: String(pagination.value.perPage),
    });
    const data = await request(`/comments?${params.toString()}`);
    comments.value = data.items || [];
    stats.value = data.stats || stats.value;
    pagination.value = data.pagination || pagination.value;
  } catch (err) {
    error.value = err.message || 'Could not load comments';
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
  { key: 'approved', label: 'Approved', count: stats.value.approved },
  { key: 'pending', label: 'Pending', count: stats.value.pending },
  { key: 'spam', label: 'Spam', count: stats.value.spam },
  { key: 'trash', label: 'Trash', count: stats.value.trash },
]);

const runStatus = async (comment, nextStatus) => {
  if (!comment || busyId.value) return;
  busyId.value = comment.id;
  error.value = '';
  notice.value = '';
  try {
    const data = await request('/comments/status', {
      method: 'POST',
      body: JSON.stringify({ id: comment.id, status: nextStatus }),
    });
    notice.value = data.message || 'Comment updated.';
    await load();
  } catch (err) {
    error.value = err.message || 'Action failed';
  } finally {
    busyId.value = 0;
  }
};

const deleteComment = async (comment) => {
  if (!comment || busyId.value) return;
  busyId.value = comment.id;
  error.value = '';
  notice.value = '';
  try {
    const data = await request(`/comments/${comment.id}`, { method: 'DELETE' });
    notice.value = data.message || 'Comment deleted.';
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

const initials = (name) =>
  (name || '?')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');

const rowRange = computed(() => {
  if (!pagination.value.total) return '0 to 0';
  const start = (pagination.value.page - 1) * pagination.value.perPage + 1;
  const end = Math.min(pagination.value.total, pagination.value.page * pagination.value.perPage);
  return `${start} to ${end}`;
});
</script>

<template>
  <main class="comments-page">
    <header class="comments-page__header">
      <div>
        <p class="comments-page__eyebrow">Discussion</p>
        <h1>Comments</h1>
      </div>
      <div class="comments-page__actions">
        <button
          type="button"
          class="icon-button"
          :class="{ 'is-active': compact }"
          title="Toggle compact view"
          @click="compact = !compact"
        >
          <span class="dashicons dashicons-menu-alt3" />
        </button>
        <button type="button" class="icon-button" title="Refresh comments" :disabled="loading" @click="load">
          <span class="dashicons dashicons-update" />
        </button>
      </div>
    </header>

    <section class="comments-page__toolbar" aria-label="Comment filters and search">
      <div class="comments-page__filters">
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
      <label class="comments-page__search">
        <span class="dashicons dashicons-search" />
        <input v-model="search" type="search" placeholder="Search comments..." />
      </label>
    </section>

    <div v-if="notice" class="comments-notice comments-notice--success">{{ notice }}</div>
    <div v-if="error" class="comments-notice comments-notice--error">{{ error }}</div>

    <section class="comments-table" :class="{ 'is-compact': compact }" aria-label="Comments">
      <div class="comments-table__head">
        <div>Author <span class="dashicons dashicons-filter" /></div>
        <div>Comment <span class="dashicons dashicons-filter" /></div>
        <div>Post <span class="dashicons dashicons-filter" /></div>
        <div>Date <span class="dashicons dashicons-filter" /></div>
        <div>Status <span class="dashicons dashicons-filter" /></div>
        <div>Actions</div>
      </div>

      <div v-if="loading" class="comments-table__empty">Loading comments...</div>
      <div v-else-if="!comments.length" class="comments-table__empty">No comments match your filters.</div>

      <article v-for="comment in comments" v-else :key="comment.id" class="comment-row">
        <div class="comment-row__author">
          <img v-if="comment.avatar" :src="comment.avatar" alt="" class="comment-row__avatar" />
          <div v-else class="comment-row__initials">{{ initials(comment.author) }}</div>
          <div class="comment-row__person">
            <strong>{{ comment.author }}</strong>
            <span>{{ comment.email || 'No email' }}</span>
          </div>
        </div>

        <div class="comment-row__comment" data-label="Comment">
          {{ comment.content || 'No comment text.' }}
        </div>

        <div class="comment-row__post" data-label="Post">
          <a v-if="comment.postEditUrl" :href="comment.postEditUrl">{{ comment.postTitle }}</a>
          <span v-else>{{ comment.postTitle }}</span>
        </div>

        <div class="comment-row__cell" data-label="Date">
          <strong>{{ comment.relativeDate }}</strong>
          <span>{{ comment.date }}</span>
        </div>

        <div class="comment-row__cell" data-label="Status">
          <span class="status-badge" :class="`status-badge--${comment.status}`">
            <i />
            {{ comment.statusLabel }}
          </span>
        </div>

        <div class="comment-row__actions">
          <button
            v-if="comment.status !== 'approved'"
            type="button"
            class="icon-button icon-button--inline"
            title="Approve"
            :disabled="busyId === comment.id"
            @click="runStatus(comment, 'approve')"
          >
            <span class="dashicons dashicons-yes" />
          </button>
          <button
            v-if="comment.status === 'approved'"
            type="button"
            class="icon-button icon-button--inline"
            title="Mark pending"
            :disabled="busyId === comment.id"
            @click="runStatus(comment, 'hold')"
          >
            <span class="dashicons dashicons-hidden" />
          </button>
          <a class="icon-button icon-button--inline" title="Edit comment" :href="comment.editUrl">
            <span class="dashicons dashicons-edit" />
          </a>
          <button
            v-if="comment.status !== 'spam'"
            type="button"
            class="icon-button icon-button--inline"
            title="Mark spam"
            :disabled="busyId === comment.id"
            @click="runStatus(comment, 'spam')"
          >
            <span class="dashicons dashicons-warning" />
          </button>
          <button
            v-if="comment.status !== 'trash'"
            type="button"
            class="icon-button icon-button--inline"
            title="Move to trash"
            :disabled="busyId === comment.id"
            @click="runStatus(comment, 'trash')"
          >
            <span class="dashicons dashicons-trash" />
          </button>
          <button
            v-else
            type="button"
            class="icon-button icon-button--inline"
            title="Delete permanently"
            :disabled="busyId === comment.id"
            @click="deleteComment(comment)"
          >
            <span class="dashicons dashicons-no-alt" />
          </button>
        </div>
      </article>

      <footer v-if="!loading && comments.length" class="comments-table__footer">
        <div>Page Size: {{ pagination.perPage }}</div>
        <div>{{ rowRange }} of {{ pagination.total }}</div>
        <div class="comments-table__pager">
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
.comments-page {
  min-height: calc(100vh - 112px);
  padding: 0;
  color: var(--aurora-text);
  background: transparent;
}
.comments-page__header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 24px;
}
.comments-page__eyebrow {
  margin: 0 0 7px;
  color: var(--aurora-text-muted);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0;
  text-transform: uppercase;
}
.comments-page h1 {
  margin: 0;
  color: var(--aurora-text);
  font-size: 1.45rem;
  line-height: 1.15;
}
.comments-page__actions,
.comments-page__toolbar,
.comments-page__filters,
.comment-row__actions {
  display: flex;
  align-items: center;
}
.comments-page__actions {
  gap: 10px;
}
.comments-page__toolbar {
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 12px;
}
.comments-page__filters {
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
.comments-page__search {
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
.comments-page__search input {
  width: 100%;
  min-width: 0;
  border: 0;
  outline: 0;
  box-shadow: none;
  background: transparent;
  color: var(--aurora-text);
  font-size: 0.84rem;
}
.comments-page__search input::placeholder {
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
.icon-button.is-active,
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
.comments-notice {
  margin: 0 0 12px;
  padding: 10px 12px;
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
  font-size: 0.78rem;
  font-weight: 750;
}
.comments-table {
  overflow: hidden;
  border: 1px solid var(--aurora-frame-border);
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-frame-bg);
}
.comments-table__head,
.comment-row {
  display: grid;
  grid-template-columns: minmax(190px, 0.9fr) minmax(320px, 1.6fr) minmax(190px, 1fr) 110px 150px 142px;
  align-items: center;
}
.comments-table__head {
  min-height: 48px;
  border-bottom: 1px solid var(--aurora-border);
  color: var(--aurora-text);
  font-size: 0.79rem;
  font-weight: 850;
}
.comments-table__head > div {
  min-height: 48px;
  padding: 0 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.comment-row {
  min-height: 76px;
  border-bottom: 1px solid var(--aurora-border);
}
.comment-row:hover {
  background: var(--aurora-bg-subtle);
}
.comment-row__author {
  display: grid;
  grid-template-columns: 36px minmax(0, 1fr);
  gap: 12px;
  align-items: center;
  min-width: 0;
  padding: 14px 16px;
}
.comment-row__avatar,
.comment-row__initials {
  width: 36px;
  height: 36px;
  border-radius: 50%;
}
.comment-row__avatar {
  display: block;
}
.comment-row__initials {
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.72rem;
  font-weight: 900;
}
.comment-row__person {
  min-width: 0;
}
.comment-row__person strong {
  display: block;
  overflow: hidden;
  color: var(--aurora-text);
  font-size: 0.86rem;
  line-height: 1.2;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.comment-row__person span,
.comment-row__cell span {
  display: block;
  overflow: hidden;
  color: var(--aurora-text-muted);
  font-size: 0.74rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.comment-row__comment,
.comment-row__post,
.comment-row__cell {
  min-width: 0;
  padding: 14px 16px;
  color: var(--aurora-text-muted);
  font-size: 0.8rem;
  line-height: 1.45;
}
.comment-row__comment {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.comment-row__post a,
.comment-row__post span {
  display: block;
  overflow: hidden;
  color: var(--aurora-text);
  font-weight: 800;
  text-decoration: none;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.comment-row__cell strong {
  display: block;
  color: var(--aurora-text-muted);
  font-size: 0.8rem;
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
  font-size: 0.74rem;
  font-weight: 850;
}
.status-badge i {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: currentColor;
  opacity: 0.8;
}
.status-badge--approved {
  background: var(--aurora-frame-bg);
  color: var(--aurora-text);
  box-shadow: inset 0 0 0 1px var(--aurora-border);
}
.comment-row__actions {
  gap: 8px;
  padding: 14px 16px;
}
.comments-table.is-compact .comment-row {
  min-height: 58px;
}
.comments-table.is-compact .comment-row__author,
.comments-table.is-compact .comment-row__comment,
.comments-table.is-compact .comment-row__post,
.comments-table.is-compact .comment-row__cell,
.comments-table.is-compact .comment-row__actions {
  padding-top: 9px;
  padding-bottom: 9px;
}
.comments-table.is-compact .comment-row__avatar,
.comments-table.is-compact .comment-row__initials {
  width: 30px;
  height: 30px;
}
.comments-table__empty {
  padding: 38px 18px;
  color: var(--aurora-text-muted);
  font-size: 0.85rem;
  text-align: center;
}
.comments-table__footer {
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
.comments-table__pager {
  display: flex;
  align-items: center;
  gap: 8px;
}
.comments-table__pager button {
  width: 28px;
  height: 28px;
  border: 0;
  border-radius: var(--aurora-radius-sm);
  background: transparent;
  color: var(--aurora-text-muted);
  cursor: pointer;
}
.comments-table__pager button:not(:disabled):hover {
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
}
.comments-table__pager button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

@media (max-width: 1280px) {
  .comments-table__head,
  .comment-row {
    grid-template-columns: minmax(170px, 0.9fr) minmax(260px, 1.4fr) minmax(160px, 0.9fr) 100px 130px 132px;
  }
}

@media (max-width: 1080px) {
  .comments-table__head {
    display: none;
  }
  .comment-row {
    grid-template-columns: 1fr;
    align-items: stretch;
    padding: 8px 0;
  }
  .comment-row__comment,
  .comment-row__post,
  .comment-row__cell,
  .comment-row__actions {
    padding: 8px 16px;
  }
  .comment-row__cell,
  .comment-row__post {
    display: flex;
    justify-content: space-between;
    gap: 16px;
  }
  .comment-row__cell::before,
  .comment-row__post::before {
    content: attr(data-label);
    color: var(--aurora-text-muted);
    font-weight: 800;
  }
  .comments-table__footer,
  .comments-page__header,
  .comments-page__toolbar {
    align-items: stretch;
    flex-direction: column;
  }
  .comments-table__footer {
    padding: 14px 16px;
    gap: 10px;
  }
  .comments-page__search {
    width: 100%;
  }
}
</style>
