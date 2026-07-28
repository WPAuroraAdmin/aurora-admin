<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import AppListShell from '../_shared/AppListShell.vue';
import AppListItem from '../_shared/AppListItem.vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const api = (path, opts = {}) =>
  fetch(`${props.serverData.restUrl}aurora-admin/v1${path}`, {
    ...opts,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': props.serverData.restNonce,
      ...(opts.headers || {}),
    },
  }).then((r) => {
    if (!r.ok) throw new Error('Request failed');
    return r.json();
  });

const menus = ref([]);
const nativeItems = ref([]);
const loading = ref(true);
const search = ref('');
const statusFilter = ref('all');
const selectedId = ref(null);
const roleOptions = ref([]);

const load = async () => {
  loading.value = true;
  try {
    menus.value = await api('/menus');
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  load();
  roleOptions.value = props.serverData.roles || [];
  // The native top-level menu is provided in serverData (captured server-side
  // at page load); the REST route can't build the admin menu.
  nativeItems.value = props.serverData.nativeItems || [];
});

const filteredMenus = computed(() => {
  let list = menus.value;
  if (statusFilter.value !== 'all') {
    list = list.filter((m) => m.status === statusFilter.value);
  }
  const q = search.value.trim().toLowerCase();
  if (q) list = list.filter((m) => m.name.toLowerCase().includes(q));
  return list;
});

const selected = computed(() => menus.value.find((m) => m.id === selectedId.value) || null);

const draft = reactive({ name: '', status: 'draft', roles: [] });

// The full ordered list of top-level items being edited. Row order IS the
// menu order (saved as each item's `order` index). Each row carries its
// native title/icon plus the per-item overrides (custom label, hidden).
const orderedItems = ref([]);

// Merge a config's saved items with the live native top-level list: saved
// items first, in their saved order, then any native items the config
// doesn't mention yet (e.g. a newly installed plugin) appended at the end,
// so new menu items are never silently dropped.
const buildOrdered = (savedItems = []) => {
  const byId = Object.fromEntries(nativeItems.value.map((n) => [n.native_id, n]));
  const saved = [...savedItems].sort((a, b) => (a.order || 0) - (b.order || 0));
  const seen = new Set();
  const result = [];
  for (const s of saved) {
    const n = byId[s.native_id];
    if (!n) continue; // saved item no longer exists in the admin menu
    result.push({ native_id: n.native_id, title: n.title, icon: n.icon, label: s.label || '', hidden: !!s.hidden });
    seen.add(s.native_id);
  }
  for (const n of nativeItems.value) {
    if (seen.has(n.native_id)) continue;
    result.push({ native_id: n.native_id, title: n.title, icon: n.icon, label: '', hidden: false });
  }
  orderedItems.value = result;
};

const selectMenu = (menu) => {
  selectedId.value = menu.id;
  Object.assign(draft, { name: menu.name, status: menu.status, roles: [...menu.roles] });
  buildOrdered(menu.items);
};

const isCreating = ref(false);
const newMenu = () => {
  selectedId.value = null;
  isCreating.value = true;
  Object.assign(draft, { name: 'New menu', status: 'draft', roles: [] });
  buildOrdered([]);
};

// Reorder controls: drag-and-drop plus keyboard-friendly up/down buttons.
const dragIndex = ref(-1);
const onDragStart = (i) => { dragIndex.value = i; };
const onDrop = (i) => {
  const from = dragIndex.value;
  dragIndex.value = -1;
  if (from < 0 || from === i) return;
  const arr = orderedItems.value;
  const [moved] = arr.splice(from, 1);
  arr.splice(i, 0, moved);
};
const move = (i, dir) => {
  const j = i + dir;
  const arr = orderedItems.value;
  if (j < 0 || j >= arr.length) return;
  [arr[i], arr[j]] = [arr[j], arr[i]];
};

const saving = ref(false);
const save = async () => {
  saving.value = true;
  try {
    const payload = {
      name: draft.name,
      status: draft.status,
      roles: draft.roles,
      // Row order becomes each item's `order`; only send items that actually
      // override something (renamed or hidden) or that need an explicit order.
      items: orderedItems.value.map((it, index) => ({
        native_id: it.native_id,
        label: it.label || '',
        hidden: !!it.hidden,
        order: index,
      })),
    };
    if (isCreating.value) {
      const created = await api('/menus', { method: 'POST', body: JSON.stringify(payload) });
      await load();
      selectMenu(created);
      isCreating.value = false;
    } else if (selectedId.value) {
      await api(`/menus/${selectedId.value}`, { method: 'PUT', body: JSON.stringify(payload) });
      await load();
    }
  } finally {
    saving.value = false;
  }
};

const remove = async () => {
  if (!selectedId.value) return;
  await api(`/menus/${selectedId.value}`, { method: 'DELETE' });
  selectedId.value = null;
  await load();
};

const toggleRole = (slug) => {
  const idx = draft.roles.indexOf(slug);
  if (idx === -1) draft.roles.push(slug);
  else draft.roles.splice(idx, 1);
};
</script>

<template>
  <AppListShell
    v-model:search="search"
    title="Menu Creator"
    search-placeholder="Search menus…"
    :count-label="`${filteredMenus.length} MENUS`"
    detail-icon="dashicons-menu-alt"
    detail-title="Menu Editor"
    detail-text="Select a menu from the list to edit its structure and settings."
    :has-selection="!!selected || isCreating"
  >
    <template #header-actions>
      <button type="button" @click="newMenu">+</button>
    </template>

    <template #filters>
      <button
        v-for="f in ['all', 'published', 'draft']"
        :key="f"
        type="button"
        :class="{ 'is-active': statusFilter === f }"
        @click="statusFilter = f"
      >
        {{ f === 'all' ? 'All' : f[0].toUpperCase() + f.slice(1) }}
      </button>
    </template>

    <template #list>
      <p v-if="!loading && !filteredMenus.length" style="font-size: 0.8125rem; color: var(--aurora-text-muted); padding: 20px 4px; text-align: center;">
        No menus yet
      </p>
      <AppListItem
        v-for="m in filteredMenus"
        :key="m.id"
        icon="dashicons-menu-alt"
        :title="m.name"
        :subtitle="m.status"
        :active="m.id === selectedId"
        @click="selectMenu(m)"
      />
    </template>

    <template #detail>
      <div class="menu-editor">
        <div class="menu-editor__row">
          <input v-model="draft.name" type="text" class="menu-editor__name" placeholder="Menu name" />
          <select v-model="draft.status" class="menu-editor__status">
            <option value="draft">Draft</option>
            <option value="published">Published</option>
          </select>
        </div>

        <div class="menu-editor__section">
          <h3>Target roles</h3>
          <div class="menu-editor__roles">
            <label v-for="r in roleOptions" :key="r.value" class="menu-editor__role">
              <input type="checkbox" :checked="draft.roles.includes(r.value)" @change="toggleRole(r.value)" />
              {{ r.label }}
            </label>
          </div>
        </div>

        <div class="menu-editor__section">
          <h3>Menu items — drag to reorder</h3>
          <div class="menu-editor__items">
            <div
              v-for="(it, i) in orderedItems"
              :key="it.native_id"
              class="menu-editor__item"
              :class="{ 'is-hidden': it.hidden }"
              draggable="true"
              @dragstart="onDragStart(i)"
              @dragover.prevent
              @drop="onDrop(i)"
            >
              <span class="menu-editor__drag dashicons dashicons-move" title="Drag to reorder" />
              <span class="dashicons" :class="it.icon?.value || 'dashicons-admin-generic'" />
              <input type="text" v-model="it.label" :placeholder="it.title" />
              <label class="menu-editor__hide">
                <input type="checkbox" v-model="it.hidden" />
                Hide
              </label>
              <div class="menu-editor__move">
                <button type="button" :disabled="i === 0" title="Move up" @click="move(i, -1)">▲</button>
                <button type="button" :disabled="i === orderedItems.length - 1" title="Move down" @click="move(i, 1)">▼</button>
              </div>
            </div>
          </div>
        </div>

        <div class="menu-editor__actions">
          <button type="button" class="is-danger" :disabled="!selectedId" @click="remove">Delete</button>
          <button type="button" class="is-primary" :disabled="saving" @click="save">
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </div>
    </template>
  </AppListShell>
</template>

<style scoped>
.menu-editor { display: flex; flex-direction: column; gap: 22px; }
.menu-editor__row { display: flex; gap: 10px; }
.menu-editor__name {
  flex: 1; font-size: 1.1rem; font-weight: 700; padding: 8px 10px;
  border: 1px solid var(--aurora-border); border-radius: var(--aurora-radius-sm);
  background: var(--aurora-bg-subtle); color: var(--aurora-text);
}
.menu-editor__status {
  padding: 8px 10px; border-radius: var(--aurora-radius-sm); border: 1px solid var(--aurora-border);
  background: var(--aurora-bg-subtle); color: var(--aurora-text);
}
.menu-editor__section h3 {
  font-size: 0.8125rem; text-transform: uppercase; letter-spacing: 0.03em;
  color: var(--aurora-text-muted); margin: 0 0 10px;
}
.menu-editor__roles { display: flex; flex-wrap: wrap; gap: 12px; }
.menu-editor__role { display: flex; align-items: center; gap: 6px; font-size: 0.8125rem; color: var(--aurora-text); }
.menu-editor__items { display: flex; flex-direction: column; gap: 6px; max-height: 360px; overflow-y: auto; }
.menu-editor__item {
  display: flex; align-items: center; gap: 10px; padding: 6px 4px;
  border-bottom: 1px solid var(--aurora-frame-border);
}
.menu-editor__item.is-hidden { opacity: 0.5; }
.menu-editor__drag { cursor: grab; color: var(--aurora-text-muted); flex-shrink: 0; }
.menu-editor__drag:active { cursor: grabbing; }
.menu-editor__item input[type='text'] {
  flex: 1; padding: 6px 8px; border-radius: var(--aurora-radius-sm); border: 1px solid var(--aurora-border);
  background: var(--aurora-bg-subtle); color: var(--aurora-text); font-size: 0.8125rem;
}
.menu-editor__hide { display: flex; align-items: center; gap: 4px; font-size: 0.75rem; color: var(--aurora-text-muted); flex-shrink: 0; }
.menu-editor__move { display: flex; flex-direction: column; gap: 2px; flex-shrink: 0; }
.menu-editor__move button {
  border: none; background: var(--aurora-bg-subtle); color: var(--aurora-text-muted);
  border-radius: 4px; width: 20px; height: 15px; line-height: 1; font-size: 9px; cursor: pointer; padding: 0;
}
.menu-editor__move button:disabled { opacity: 0.35; cursor: default; }
.menu-editor__move button:not(:disabled):hover { color: var(--aurora-accent); }
.menu-editor__actions { display: flex; justify-content: flex-end; gap: 8px; }
.menu-editor__actions button {
  border: none; border-radius: var(--aurora-radius-sm); padding: 9px 18px; font-size: 0.875rem; cursor: pointer;
  background: var(--aurora-bg-subtle); color: var(--aurora-text);
}
.menu-editor__actions .is-primary { background: var(--aurora-accent); color: #fff; }
.menu-editor__actions .is-danger { color: #ef4444; }
.menu-editor__actions button:disabled { opacity: 0.5; cursor: default; }
</style>
