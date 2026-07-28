<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import MediaFolderTree from './MediaFolderTree.vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

// Media Folders is a core feature — always on, no gating.
const foldersAvailable = true;

const items = ref([]);
const stats = ref({ all: 0, image: 0, audio: 0, video: 0, application: 0, unattached: 0 });
const pagination = ref({ page: 1, perPage: 24, total: 0, totalPages: 1 });
const loading = ref(true);
const busyId = ref(0);
const error = ref('');
const notice = ref('');
const search = ref('');
const type = ref('all');

// Media Folders (paid) state — folderId 0 = All Media, -1 = Uncategorized,
// matching MediaFolders::ALL_MEDIA / ::UNCATEGORIZED on the PHP side.
const folderId = ref(0);
const folderTree = ref([]);
const allMediaCount = ref(0);
const uncategorizedCount = ref(0);
const foldersLoading = ref(false);
const expanded = reactive({});
const moveMenuFor = ref(0);

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
      type: type.value,
      search: search.value.trim(),
      page: String(pagination.value.page),
      perPage: String(pagination.value.perPage),
    });
    params.set('folderId', String(folderId.value));
    const data = await request(`/media?${params.toString()}`);
    items.value = data.items || [];
    stats.value = data.stats || stats.value;
    pagination.value = data.pagination || pagination.value;
  } catch (err) {
    error.value = err.message || 'Could not load media';
  } finally {
    loading.value = false;
  }
};

const loadFolders = async () => {
  foldersLoading.value = true;
  try {
    const data = await request('/media-folders');
    folderTree.value = data.tree || [];
    allMediaCount.value = data.allMediaCount || 0;
    uncategorizedCount.value = data.uncategorizedCount || 0;
  } catch (err) {
    error.value = err.message || 'Could not load folders';
  } finally {
    foldersLoading.value = false;
  }
};

const flattenFolders = (nodes, depth = 0, out = []) => {
  for (const node of nodes) {
    out.push({ id: node.id, name: node.name, depth });
    if (node.children && node.children.length) flattenFolders(node.children, depth + 1, out);
  }
  return out;
};
const flatFolders = computed(() => flattenFolders(folderTree.value));

const selectFolder = (id) => {
  folderId.value = id;
  pagination.value.page = 1;
  load();
};

const toggleFolder = (id) => {
  expanded[id] = !expanded[id];
};

const createFolder = async (parent) => {
  const name = window.prompt('New folder name:');
  if (!name) return;
  try {
    await request('/media-folders', { method: 'POST', body: JSON.stringify({ name, parent }) });
    if (parent) expanded[parent] = true;
    await loadFolders();
  } catch (err) {
    error.value = err.message || 'Could not create folder';
  }
};

const renameFolder = async (node) => {
  const name = window.prompt('Rename folder:', node.name);
  if (!name || name === node.name) return;
  try {
    await request(`/media-folders/${node.id}`, { method: 'POST', body: JSON.stringify({ name }) });
    await loadFolders();
  } catch (err) {
    error.value = err.message || 'Could not rename folder';
  }
};

const deleteFolder = async (node) => {
  if (!window.confirm(`Delete "${node.name}"? Its media will become uncategorized.`)) return;
  try {
    await request(`/media-folders/${node.id}`, { method: 'DELETE' });
    if (folderId.value === node.id) selectFolder(0);
    else await loadFolders();
  } catch (err) {
    error.value = err.message || 'Could not delete folder';
  }
};

const reparentFolder = async ({ id, parent }) => {
  try {
    await request(`/media-folders/${id}`, { method: 'POST', body: JSON.stringify({ parent }) });
    await loadFolders();
  } catch (err) {
    error.value = err.message || 'Could not move folder';
  }
};

const assignToFolder = async (folderIdTarget, attachmentIds) => {
  try {
    await request('/media-folders/assign', {
      method: 'POST',
      body: JSON.stringify({ folderId: folderIdTarget, attachmentIds }),
    });
    moveMenuFor.value = 0;
    await Promise.all([load(), loadFolders()]);
  } catch (err) {
    error.value = err.message || 'Could not move media';
  }
};

onMounted(() => {
  load();
  loadFolders();
});

let searchTimer = 0;
watch(search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    pagination.value.page = 1;
    load();
  }, 250);
});

const setType = (next) => {
  type.value = next;
  pagination.value.page = 1;
  load();
};

const filters = computed(() => [
  { key: 'all', label: 'All', count: stats.value.all },
  { key: 'image', label: 'Images', count: stats.value.image },
  { key: 'audio', label: 'Audio', count: stats.value.audio },
  { key: 'video', label: 'Video', count: stats.value.video },
  { key: 'application', label: 'Documents', count: stats.value.application },
  { key: 'unattached', label: 'Unattached', count: stats.value.unattached },
]);

const deleteItem = async (item) => {
  if (!item || busyId.value) return;
  if (!window.confirm(`Permanently delete "${item.title}"? This cannot be undone.`)) return;
  busyId.value = item.id;
  error.value = '';
  notice.value = '';
  try {
    const data = await request(`/media/${item.id}`, { method: 'DELETE' });
    notice.value = data.message || 'Media item deleted.';
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

const typeIcon = (mimeType) => {
  const group = (mimeType || '').split('/')[0];
  if (group === 'audio') return 'dashicons-format-audio';
  if (group === 'video') return 'dashicons-format-video';
  if (mimeType === 'application/pdf') return 'dashicons-pdf';
  return 'dashicons-media-document';
};
</script>

<template>
  <main class="media-page">
    <header class="media-page__header">
      <div>
        <p class="media-page__eyebrow">Content</p>
        <h1>Media Library</h1>
      </div>
      <div class="media-page__actions">
        <a class="button-primary" :href="serverData.newMediaUrl">Add Media File</a>
        <button type="button" class="icon-button" title="Refresh media" :disabled="loading" @click="load">
          <span class="dashicons dashicons-update" />
        </button>
      </div>
    </header>

    <div class="media-page__body">
      <aside class="media-page__sidebar">
          <div class="mf-pinned">
            <button
              type="button"
              class="mf-pinned__row"
              :class="{ 'mf-pinned__row--active': folderId === 0 }"
              @dragover.prevent
              @drop.prevent="
                (e) => {
                  const p = e.dataTransfer.getData('text/plain');
                  if (p.startsWith('aurora-attachments:')) assignToFolder(0, p.split(':')[1].split(',').map(Number));
                }
              "
              @click="selectFolder(0)"
            >
              <span class="dashicons dashicons-admin-media" />
              <span>All Media</span>
              <span class="mf-pinned__count">{{ allMediaCount }}</span>
            </button>
            <button
              type="button"
              class="mf-pinned__row"
              :class="{ 'mf-pinned__row--active': folderId === -1 }"
              @dragover.prevent
              @drop.prevent="
                (e) => {
                  const p = e.dataTransfer.getData('text/plain');
                  if (p.startsWith('aurora-attachments:')) assignToFolder(-1, p.split(':')[1].split(',').map(Number));
                }
              "
              @click="selectFolder(-1)"
            >
              <span class="dashicons dashicons-editor-help" />
              <span>Uncategorized</span>
              <span class="mf-pinned__count">{{ uncategorizedCount }}</span>
            </button>
          </div>

          <div class="mf-sidebar__header">
            <span>Folders</span>
            <button type="button" class="icon-button icon-button--tiny" title="New folder" @click="createFolder(0)">
              <span class="dashicons dashicons-plus-alt2" />
            </button>
          </div>

          <p v-if="foldersLoading" class="mf-sidebar__loading">Loading folders…</p>
          <p v-else-if="!folderTree.length" class="mf-sidebar__empty">No folders yet.</p>
          <MediaFolderTree
            v-else
            :nodes="folderTree"
            :active-folder-id="folderId"
            :expanded="expanded"
            @select="selectFolder"
            @toggle="toggleFolder"
            @create-child="createFolder"
            @rename="renameFolder"
            @delete="deleteFolder"
            @reparent="reparentFolder"
            @assign="({ folderId: fid, attachmentIds }) => assignToFolder(fid, attachmentIds)"
          />
      </aside>

      <div class="media-page__main">
        <section class="media-page__toolbar" aria-label="Media filters and search">
          <div class="media-page__filters">
            <button
              v-for="filter in filters"
              :key="filter.key"
              type="button"
              class="filter-pill"
              :class="{ 'is-active': type === filter.key }"
              @click="setType(filter.key)"
            >
              <span>{{ filter.label }}</span>
              <strong>{{ filter.count }}</strong>
            </button>
          </div>
          <label class="media-page__search">
            <span class="dashicons dashicons-search" />
            <input v-model="search" type="search" placeholder="Search media..." />
          </label>
        </section>

        <div v-if="notice" class="media-notice media-notice--success">{{ notice }}</div>
        <div v-if="error" class="media-notice media-notice--error">{{ error }}</div>

        <div v-if="loading" class="media-grid__empty">Loading media...</div>
        <div v-else-if="!items.length" class="media-grid__empty">No media items match your filters.</div>

        <section v-else class="media-grid" aria-label="Media items">
          <article
            v-for="item in items"
            :key="item.id"
            class="media-card"
            :draggable="foldersAvailable"
            @dragstart="foldersAvailable && $event.dataTransfer.setData('text/plain', `aurora-attachments:${item.id}`)"
          >
            <div class="media-card__preview">
              <img v-if="item.isImage && item.thumbnailUrl" :src="item.thumbnailUrl" :alt="item.title" />
              <span v-else class="dashicons media-card__icon" :class="typeIcon(item.mimeType)" />
              <div class="media-card__overlay">
                <a class="icon-button icon-button--inline" title="Edit" :href="item.editUrl">
                  <span class="dashicons dashicons-edit" />
                </a>
                <a
                  class="icon-button icon-button--inline"
                  title="View file"
                  :href="item.fileUrl"
                  target="_blank"
                  rel="noopener"
                >
                  <span class="dashicons dashicons-visibility" />
                </a>
                <button
                  v-if="foldersAvailable"
                  type="button"
                  class="icon-button icon-button--inline"
                  title="Move to folder"
                  @click.stop="moveMenuFor = moveMenuFor === item.id ? 0 : item.id"
                >
                  <span class="dashicons dashicons-portfolio" />
                </button>
                <button
                  v-if="item.canDelete"
                  type="button"
                  class="icon-button icon-button--inline"
                  title="Delete permanently"
                  :disabled="busyId === item.id"
                  @click="deleteItem(item)"
                >
                  <span class="dashicons dashicons-trash" />
                </button>
              </div>
              <div v-if="moveMenuFor === item.id" class="media-card__move-menu" @click.stop>
                <button type="button" @click="assignToFolder(0, [item.id])">Uncategorized</button>
                <button
                  v-for="f in flatFolders"
                  :key="f.id"
                  type="button"
                  :style="{ paddingLeft: 10 + f.depth * 14 + 'px' }"
                  @click="assignToFolder(f.id, [item.id])"
                >
                  {{ f.name }}
                </button>
              </div>
            </div>
            <div class="media-card__meta">
              <strong :title="item.title">{{ item.title }}</strong>
              <span>{{ item.typeLabel }}<template v-if="item.dimensions"> · {{ item.dimensions }}</template></span>
              <span v-if="item.attachedToTitle">
                Attached to
                <a v-if="item.attachedToEditUrl" :href="item.attachedToEditUrl">{{ item.attachedToTitle }}</a>
                <template v-else>{{ item.attachedToTitle }}</template>
              </span>
              <span v-else class="media-card__unattached">Unattached</span>
              <span>{{ item.date }}<template v-if="item.fileSize"> · {{ item.fileSize }}</template></span>
            </div>
          </article>
        </section>

        <footer v-if="!loading && items.length" class="media-page__footer">
      <div>Page Size: {{ pagination.perPage }}</div>
      <div>{{ rowRange }} of {{ pagination.total }}</div>
      <div class="media-page__pager">
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
      </div>
    </div>
  </main>
</template>

<style scoped>
.media-page {
  min-height: calc(100vh - 112px);
  padding: 0;
  color: var(--aurora-text);
  background: transparent;
}
.media-page__header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 24px;
}
.media-page__eyebrow {
  margin: 0 0 7px;
  color: var(--aurora-text-muted);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0;
  text-transform: uppercase;
}
.media-page h1 {
  margin: 0;
  color: var(--aurora-text);
  font-size: 1.45rem;
  line-height: 1.15;
}
.media-page__actions,
.media-page__toolbar,
.media-page__filters,
.media-page__pager {
  display: flex;
  align-items: center;
}
.media-page__actions {
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
.media-page__toolbar {
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 16px;
}
.media-page__filters {
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
.media-page__search {
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
.media-page__search input {
  width: 100%;
  min-width: 0;
  border: 0;
  outline: 0;
  box-shadow: none;
  background: transparent;
  color: var(--aurora-text);
  font-size: 0.84rem;
}
.media-page__search input::placeholder {
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
  width: 34px;
  min-height: 34px;
  border: 1px solid rgba(255, 255, 255, 0.25);
  background: rgba(0, 0, 0, 0.45);
  color: #fff;
}
.icon-button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.media-notice {
  margin: 0 0 12px;
  padding: 10px 12px;
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
  font-size: 0.78rem;
  font-weight: 750;
}
.media-grid__empty {
  padding: 60px 18px;
  border: 1px solid var(--aurora-frame-border);
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-frame-bg);
  color: var(--aurora-text-muted);
  font-size: 0.85rem;
  text-align: center;
}
.media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 16px;
}
.media-card {
  border: 1px solid var(--aurora-frame-border);
  border-radius: var(--aurora-radius-md);
  background: var(--aurora-frame-bg);
  overflow: hidden;
}
.media-card__preview {
  position: relative;
  aspect-ratio: 1;
  background: var(--aurora-bg-subtle);
  display: flex;
  align-items: center;
  justify-content: center;
}
.media-card__preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.media-card__icon {
  font-size: 46px;
  width: 46px;
  height: 46px;
  color: var(--aurora-text-muted);
}
.media-card__overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: rgba(0, 0, 0, 0.4);
  opacity: 0;
  transition: opacity 0.15s ease;
}
.media-card:hover .media-card__overlay {
  opacity: 1;
}
.media-card__meta {
  padding: 10px 12px 12px;
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.media-card__meta strong {
  overflow: hidden;
  color: var(--aurora-text);
  font-size: 0.8rem;
  font-weight: 750;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.media-card__meta span {
  overflow: hidden;
  color: var(--aurora-text-muted);
  font-size: 0.72rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.media-card__meta a {
  color: var(--aurora-accent);
  text-decoration: none;
}
.media-card__unattached {
  font-style: italic;
}
.media-page__footer {
  min-height: 54px;
  margin-top: 18px;
  border-top: 1px solid var(--aurora-border);
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 26px;
  padding: 14px 4px 0;
  color: var(--aurora-text-muted);
  font-size: 0.8rem;
}
.media-page__pager {
  gap: 8px;
}
.media-page__pager button {
  width: 28px;
  height: 28px;
  border: 0;
  border-radius: var(--aurora-radius-sm);
  background: transparent;
  color: var(--aurora-text-muted);
  cursor: pointer;
}
.media-page__pager button:not(:disabled):hover {
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
}
.media-page__pager button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

@media (max-width: 1080px) {
  .media-page__header,
  .media-page__toolbar,
  .media-page__footer {
    align-items: stretch;
    flex-direction: column;
  }
  .media-page__search {
    width: 100%;
  }
  .media-page__footer {
    gap: 10px;
  }
  .media-page__body {
    flex-direction: column;
  }
  .media-page__sidebar {
    width: 100%;
  }
}

.media-page__body {
  display: flex;
  align-items: flex-start;
  gap: 20px;
}
.media-page__sidebar {
  flex-shrink: 0;
  width: 220px;
  padding: 12px;
  border: 1px solid var(--aurora-frame-border);
  border-radius: var(--aurora-radius-md);
  background: var(--aurora-frame-bg);
}
.media-page__main {
  flex: 1;
  min-width: 0;
}
.mf-pinned {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-bottom: 10px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--aurora-border);
}
.mf-pinned__row {
  display: flex;
  align-items: center;
  gap: 8px;
  border: none;
  border-radius: var(--aurora-radius-sm);
  background: none;
  padding: 6px 6px;
  color: var(--aurora-text);
  font-size: 0.8125rem;
  cursor: pointer;
  text-align: left;
}
.mf-pinned__row:hover {
  background: var(--aurora-bg-subtle);
}
.mf-pinned__row--active {
  background: var(--aurora-accent-soft);
}
.mf-pinned__row .dashicons {
  font-size: 15px;
  width: 15px;
  height: 15px;
  color: var(--aurora-text-muted);
}
.mf-pinned__row span:nth-child(2) {
  flex: 1;
}
.mf-pinned__count {
  font-size: 0.7rem;
  color: var(--aurora-text-muted);
}
.mf-sidebar__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
  color: var(--aurora-text-muted);
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
}
.icon-button--tiny {
  width: 22px;
  min-height: 22px;
  border: none;
  background: none;
  color: var(--aurora-text-muted);
}
.icon-button--tiny:hover {
  color: var(--aurora-text);
}
.mf-sidebar__loading,
.mf-sidebar__empty {
  margin: 0;
  color: var(--aurora-text-muted);
  font-size: 0.75rem;
}
.media-card__move-menu {
  position: absolute;
  z-index: 10;
  right: 8px;
  bottom: 8px;
  max-height: 160px;
  min-width: 150px;
  overflow-y: auto;
  border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-frame-bg);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
  padding: 4px;
}
.media-card__move-menu button {
  display: block;
  width: 100%;
  padding: 6px 10px;
  border: none;
  background: none;
  border-radius: var(--aurora-radius-sm);
  color: var(--aurora-text);
  font-size: 0.78rem;
  text-align: left;
  cursor: pointer;
}
.media-card__move-menu button:hover {
  background: var(--aurora-bg-subtle);
}
</style>
