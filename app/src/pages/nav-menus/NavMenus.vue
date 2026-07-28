<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const api = (path) => `${props.serverData.restUrl}aurora-admin/v1${path}`;
const headers = () => ({ 'Content-Type': 'application/json', 'X-WP-Nonce': props.serverData.restNonce });

const menus = ref([]);
const activeMenuId = ref(null);
const items = ref([]);
const locations = ref([]);
const pickers = ref({ pages: [], posts: [], categories: [] });
const loading = ref(false);
const error = ref('');
const notice = ref('');

const activeMenu = computed(() => menus.value.find((m) => m.id === activeMenuId.value));

// Flat list, ordered so parents appear before children and children are
// visually indented — simpler than a tree render, same end result.
const orderedItems = computed(() => {
  const byParent = {};
  items.value.forEach((i) => {
    (byParent[i.parent] ||= []).push(i);
  });
  Object.values(byParent).forEach((list) => list.sort((a, b) => a.order - b.order));

  const walk = (parentId, depth) => {
    const out = [];
    (byParent[parentId] || []).forEach((item) => {
      out.push({ ...item, depth });
      out.push(...walk(item.id, depth + 1));
    });
    return out;
  };
  return walk(0, 0);
});

const loadMenus = async () => {
  try {
    const res = await fetch(api('/nav-menus'), { headers: headers() });
    const data = await res.json();
    if (!res.ok) throw new Error(data?.message || 'Could not load menus.');
    menus.value = data;
    if (!activeMenuId.value && menus.value.length) {
      activeMenuId.value = menus.value[0].id;
    }
  } catch (e) {
    error.value = e.message || 'Could not load menus.';
  }
};

const loadItems = async () => {
  if (!activeMenuId.value) {
    items.value = [];
    return;
  }
  loading.value = true;
  try {
    const res = await fetch(api(`/nav-menus/${activeMenuId.value}/items`), { headers: headers() });
    const data = await res.json();
    if (!res.ok) throw new Error(data?.message || 'Could not load menu items.');
    items.value = data;
  } catch (e) {
    error.value = e.message || 'Could not load menu items.';
  } finally {
    loading.value = false;
  }
};

const loadLocations = async () => {
  try {
    const res = await fetch(api('/nav-menus/locations'), { headers: headers() });
    const data = await res.json();
    if (!res.ok) throw new Error(data?.message || 'Could not load locations.');
    locations.value = data;
  } catch (e) {
    error.value = e.message || 'Could not load locations.';
  }
};

const loadPickers = async () => {
  try {
    const res = await fetch(api('/nav-menus/pickers'), { headers: headers() });
    const data = await res.json();
    if (!res.ok) throw new Error(data?.message || 'Could not load pickers.');
    pickers.value = data;
  } catch (e) {
    error.value = e.message || 'Could not load pickers.';
  }
};

onMounted(async () => {
  await Promise.all([loadMenus(), loadLocations(), loadPickers()]);
  await loadItems();
});

const selectMenu = async (id) => {
  activeMenuId.value = id;
  await loadItems();
};

// Create menu -----------------------------------------------------------
const newMenuName = ref('');
const creatingMenu = ref(false);
const createMenu = async () => {
  if (!newMenuName.value.trim()) return;
  creatingMenu.value = true;
  try {
    const res = await fetch(api('/nav-menus'), {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify({ name: newMenuName.value.trim() }),
    });
    const menu = await res.json();
    if (!res.ok) throw new Error(menu?.message || 'Could not create menu.');
    menus.value.push(menu);
    newMenuName.value = '';
    await selectMenu(menu.id);
  } catch (e) {
    error.value = e.message;
  } finally {
    creatingMenu.value = false;
  }
};

const deleteMenu = async () => {
  if (!activeMenu.value) return;
  if (!confirm(`Delete menu "${activeMenu.value.name}"? This can't be undone.`)) return;
  await fetch(api(`/nav-menus/${activeMenuId.value}`), { method: 'DELETE', headers: headers() });
  menus.value = menus.value.filter((m) => m.id !== activeMenuId.value);
  activeMenuId.value = menus.value[0]?.id || null;
  await loadItems();
};

// Add items ---------------------------------------------------------------
const addTab = ref('pages');
const customLink = ref({ title: '', url: 'https://' });
const selectedIds = ref([]);

const toggleSelected = (id) => {
  const i = selectedIds.value.indexOf(id);
  if (i === -1) selectedIds.value.push(id);
  else selectedIds.value.splice(i, 1);
};

const addSelectedItems = async () => {
  if (!activeMenuId.value) return;
  const list = pickers.value[addTab.value] || [];
  const toAdd = list.filter((p) => selectedIds.value.includes(p.id));
  for (const obj of toAdd) {
    const type = addTab.value === 'pages' ? 'page' : addTab.value === 'posts' ? 'post' : 'category';
    await fetch(api(`/nav-menus/${activeMenuId.value}/items`), {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify({ type, objectId: obj.id, title: obj.title, parent: 0, order: items.value.length + 1 }),
    });
  }
  selectedIds.value = [];
  await loadItems();
  await loadMenus();
};

const addCustomLink = async () => {
  if (!activeMenuId.value || !customLink.value.url) return;
  await fetch(api(`/nav-menus/${activeMenuId.value}/items`), {
    method: 'POST',
    headers: headers(),
    body: JSON.stringify({
      type: 'custom',
      url: customLink.value.url,
      title: customLink.value.title || customLink.value.url,
      parent: 0,
      order: items.value.length + 1,
    }),
  });
  customLink.value = { title: '', url: 'https://' };
  await loadItems();
  await loadMenus();
};

// Structure editing -------------------------------------------------------
const removeItem = async (id) => {
  await fetch(api(`/nav-menus/items/${id}`), { method: 'DELETE', headers: headers() });
  await loadItems();
  await loadMenus();
};

const persistOrder = async () => {
  const payload = orderedItems.value.map((it, idx) => ({ id: it.id, parent: it.parent, order: idx + 1 }));
  await fetch(api(`/nav-menus/${activeMenuId.value}/reorder`), {
    method: 'POST',
    headers: headers(),
    body: JSON.stringify({ items: payload }),
  });
};

const moveUp = async (item) => {
  const idx = items.value.findIndex((i) => i.id === item.id);
  const siblings = items.value.filter((i) => i.parent === item.parent).sort((a, b) => a.order - b.order);
  const pos = siblings.findIndex((i) => i.id === item.id);
  if (pos <= 0) return;
  [siblings[pos - 1].order, siblings[pos].order] = [siblings[pos].order, siblings[pos - 1].order];
  await persistOrder();
  await loadItems();
};

const moveDown = async (item) => {
  const siblings = items.value.filter((i) => i.parent === item.parent).sort((a, b) => a.order - b.order);
  const pos = siblings.findIndex((i) => i.id === item.id);
  if (pos === -1 || pos >= siblings.length - 1) return;
  [siblings[pos + 1].order, siblings[pos].order] = [siblings[pos].order, siblings[pos + 1].order];
  await persistOrder();
  await loadItems();
};

const indent = async (item) => {
  const siblings = items.value.filter((i) => i.parent === item.parent).sort((a, b) => a.order - b.order);
  const pos = siblings.findIndex((i) => i.id === item.id);
  if (pos <= 0) return;
  item.parent = siblings[pos - 1].id;
  await persistOrder();
  await loadItems();
};

const outdent = async (item) => {
  const parentItem = items.value.find((i) => i.id === item.parent);
  if (!parentItem) return;
  item.parent = parentItem.parent;
  await persistOrder();
  await loadItems();
};

// Locations ---------------------------------------------------------------
const assignLocation = async (slug, menuId) => {
  await fetch(api('/nav-menus/locations'), {
    method: 'POST',
    headers: headers(),
    body: JSON.stringify({ location: slug, menuId: menuId || 0 }),
  });
  notice.value = 'Location updated.';
  setTimeout(() => (notice.value = ''), 2000);
  await loadLocations();
};
</script>

<template>
  <main class="nav-menus-page">
    <header class="nav-menus-page__header">
      <div>
        <p class="nav-menus-page__eyebrow">Appearance</p>
        <h1>Menus</h1>
      </div>
    </header>

    <div v-if="notice" class="nav-menus-notice nav-menus-notice--success">{{ notice }}</div>
    <div v-if="error" class="nav-menus-notice nav-menus-notice--error">{{ error }}</div>

    <div class="nav-menus-layout">
      <!-- Left: menu list + locations -->
      <aside class="nav-menus-side">
        <h2>Your Menus</h2>
        <ul class="nav-menus-list">
          <li v-for="menu in menus" :key="menu.id">
            <button
              type="button"
              class="nav-menus-list__item"
              :class="{ 'is-active': menu.id === activeMenuId }"
              @click="selectMenu(menu.id)"
            >
              {{ menu.name }} <span>({{ menu.itemCount }})</span>
            </button>
          </li>
        </ul>

        <div class="nav-menus-create">
          <input v-model="newMenuName" type="text" placeholder="New menu name" @keyup.enter="createMenu" />
          <button type="button" class="button" :disabled="creatingMenu" @click="createMenu">Create</button>
        </div>

        <h2>Manage Locations</h2>
        <div v-for="loc in locations" :key="loc.slug" class="nav-menus-location">
          <label>{{ loc.description }}</label>
          <select :value="loc.menuId || ''" @change="assignLocation(loc.slug, $event.target.value ? Number($event.target.value) : 0)">
            <option value="">— Not assigned —</option>
            <option v-for="menu in menus" :key="menu.id" :value="menu.id">{{ menu.name }}</option>
          </select>
        </div>
        <p v-if="!locations.length" class="nav-menus-empty">This theme registered no menu locations.</p>
      </aside>

      <!-- Middle: add items -->
      <section class="nav-menus-add">
        <h2>Add Menu Items</h2>
        <div class="nav-menus-add__tabs">
          <button type="button" :class="{ 'is-active': addTab === 'pages' }" @click="addTab = 'pages'">Pages</button>
          <button type="button" :class="{ 'is-active': addTab === 'posts' }" @click="addTab = 'posts'">Posts</button>
          <button type="button" :class="{ 'is-active': addTab === 'categories' }" @click="addTab = 'categories'">Categories</button>
          <button type="button" :class="{ 'is-active': addTab === 'custom' }" @click="addTab = 'custom'">Custom Link</button>
        </div>

        <div v-if="addTab !== 'custom'" class="nav-menus-add__list">
          <label v-for="opt in pickers[addTab]" :key="opt.id" class="nav-menus-add__row">
            <input type="checkbox" :checked="selectedIds.includes(opt.id)" @change="toggleSelected(opt.id)" />
            <span>{{ opt.title }}</span>
          </label>
          <p v-if="!pickers[addTab]?.length" class="nav-menus-empty">Nothing to show.</p>
          <button v-if="pickers[addTab]?.length" type="button" class="button-primary" :disabled="!activeMenuId" @click="addSelectedItems">
            Add to Menu
          </button>
        </div>

        <div v-else class="nav-menus-add__custom">
          <input v-model="customLink.title" type="text" placeholder="Link Text" />
          <input v-model="customLink.url" type="text" placeholder="https://example.com" />
          <button type="button" class="button-primary" :disabled="!activeMenuId" @click="addCustomLink">Add to Menu</button>
        </div>
      </section>

      <!-- Right: current structure -->
      <section class="nav-menus-structure">
        <div class="nav-menus-structure__head">
          <h2>{{ activeMenu?.name || 'Select a menu' }}</h2>
          <button v-if="activeMenu" type="button" class="icon-button" title="Delete menu" @click="deleteMenu">
            <span class="dashicons dashicons-trash" />
          </button>
        </div>

        <p v-if="loading" class="nav-menus-empty">Loading…</p>
        <p v-else-if="!orderedItems.length" class="nav-menus-empty">No items in this menu yet — add some from the left.</p>

        <div
          v-for="item in orderedItems"
          :key="item.id"
          class="nav-menus-item"
          :style="{ marginLeft: `${item.depth * 24}px` }"
        >
          <span class="nav-menus-item__title">{{ item.title }}</span>
          <span class="nav-menus-item__actions">
            <button type="button" title="Move up" @click="moveUp(item)"><span class="dashicons dashicons-arrow-up-alt2" /></button>
            <button type="button" title="Move down" @click="moveDown(item)"><span class="dashicons dashicons-arrow-down-alt2" /></button>
            <button type="button" title="Indent (make sub-item)" @click="indent(item)"><span class="dashicons dashicons-arrow-right-alt2" /></button>
            <button type="button" title="Outdent" :disabled="!item.parent" @click="outdent(item)"><span class="dashicons dashicons-arrow-left-alt2" /></button>
            <button type="button" title="Remove" @click="removeItem(item.id)"><span class="dashicons dashicons-no-alt" /></button>
          </span>
        </div>
      </section>
    </div>
  </main>
</template>

<style scoped>
.nav-menus-page { max-width: 1200px; margin: 0 auto; }
.nav-menus-page__header { margin-bottom: 16px; }
.nav-menus-page__eyebrow {
  margin: 0 0 4px; font-size: 0.75rem; text-transform: uppercase;
  letter-spacing: 0.06em; color: var(--aurora-text-muted);
}
.nav-menus-page h1 { margin: 0; font-size: 1.6rem; font-weight: 700; color: var(--aurora-text); }

.nav-menus-notice { padding: 10px 14px; border-radius: var(--aurora-radius-sm); margin-bottom: 16px; font-size: 0.875rem; }
.nav-menus-notice--success { background: rgba(34, 197, 94, 0.12); color: #22c55e; }
.nav-menus-notice--error { background: rgba(239, 68, 68, 0.12); color: #ef4444; }

.nav-menus-layout { display: grid; grid-template-columns: 260px 300px 1fr; gap: 20px; align-items: start; }
.nav-menus-side, .nav-menus-add, .nav-menus-structure {
  background: var(--aurora-bg-subtle); border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-md); padding: 18px;
}
.nav-menus-side h2, .nav-menus-add h2, .nav-menus-structure h2 {
  font-size: 0.9375rem; margin: 0 0 10px; color: var(--aurora-text);
}
.nav-menus-list { list-style: none; margin: 0 0 14px; padding: 0; }
.nav-menus-list__item {
  width: 100%; text-align: left; padding: 8px 10px; border-radius: var(--aurora-radius-sm);
  border: none; background: none; color: var(--aurora-text-muted); cursor: pointer;
  font-size: 0.875rem;
}
.nav-menus-list__item span { color: var(--aurora-text-muted); }
.nav-menus-list__item:hover { background: var(--aurora-bg); }
.nav-menus-list__item.is-active { background: var(--aurora-accent-soft); color: var(--aurora-accent); }

.nav-menus-create { display: flex; gap: 6px; margin-bottom: 20px; }
.nav-menus-create input {
  flex: 1; padding: 7px 10px; border-radius: var(--aurora-radius-sm); border: 1px solid var(--aurora-border);
  background: var(--aurora-bg); color: var(--aurora-text); font-size: 0.8125rem;
}

.nav-menus-location { display: flex; flex-direction: column; gap: 4px; margin-bottom: 10px; }
.nav-menus-location label { font-size: 0.8125rem; color: var(--aurora-text-muted); }
.nav-menus-location select,
.nav-menus-add__custom input {
  padding: 7px 10px; border-radius: var(--aurora-radius-sm); border: 1px solid var(--aurora-border);
  background: var(--aurora-bg); color: var(--aurora-text); font-size: 0.8125rem;
}

.nav-menus-add__tabs { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.nav-menus-add__tabs button {
  padding: 6px 10px; border-radius: var(--aurora-radius-sm); border: 1px solid var(--aurora-border);
  background: var(--aurora-bg); color: var(--aurora-text-muted); font-size: 0.75rem; cursor: pointer;
}
.nav-menus-add__tabs button.is-active { background: var(--aurora-accent-soft); color: var(--aurora-accent); border-color: transparent; }

.nav-menus-add__list { max-height: 320px; overflow-y: auto; display: flex; flex-direction: column; gap: 4px; }
.nav-menus-add__row {
  display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: var(--aurora-radius-sm);
  font-size: 0.8125rem; color: var(--aurora-text);
}
.nav-menus-add__row:hover { background: var(--aurora-bg); }
.nav-menus-add__list .button-primary { margin-top: 10px; width: 100%; }

.nav-menus-add__custom { display: flex; flex-direction: column; gap: 8px; }

.nav-menus-structure__head { display: flex; align-items: center; justify-content: space-between; }
.nav-menus-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px 12px; border-radius: var(--aurora-radius-sm); margin-bottom: 4px;
  background: var(--aurora-bg); border: 1px solid var(--aurora-border);
  font-size: 0.8125rem; color: var(--aurora-text);
}
.nav-menus-item__actions { display: flex; gap: 2px; }
.nav-menus-item__actions button {
  border: none; background: none; color: var(--aurora-text-muted); cursor: pointer;
  width: 26px; height: 26px; border-radius: var(--aurora-radius-sm); display: inline-flex; align-items: center; justify-content: center;
}
.nav-menus-item__actions button:hover { background: var(--aurora-bg-subtle); color: var(--aurora-text); }
.nav-menus-item__actions button:disabled { opacity: 0.35; cursor: default; }

.nav-menus-empty { color: var(--aurora-text-muted); font-size: 0.8125rem; }

@media (max-width: 1100px) {
  .nav-menus-layout { grid-template-columns: 1fr; }
}
</style>
