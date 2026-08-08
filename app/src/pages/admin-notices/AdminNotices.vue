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

const notices = ref([]);
const loading = ref(true);
const search = ref('');
const selectedId = ref(null);
const isCreating = ref(false);
const roleOptions = ref([]);

const load = async () => {
  loading.value = true;
  try {
    notices.value = await api('/notices');
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  load();
  roleOptions.value = props.serverData.roles || [];
});

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return notices.value;
  return notices.value.filter((n) => n.title.toLowerCase().includes(q));
});

const selected = computed(() => notices.value.find((n) => n.id === selectedId.value) || null);

const draft = reactive({
  title: '', message: '', type: 'info', roles: [], pages: '', dismissible: true, active: true,
});

const selectNotice = (n) => {
  selectedId.value = n.id;
  isCreating.value = false;
  Object.assign(draft, {
    title: n.title, message: n.message, type: n.type, roles: [...n.roles],
    pages: n.pages.join(', '), dismissible: n.dismissible, active: n.active,
  });
};

const newNotice = () => {
  selectedId.value = null;
  isCreating.value = true;
  Object.assign(draft, { title: 'New notice', message: '', type: 'info', roles: [], pages: '', dismissible: true, active: true });
};

const toggleRole = (slug) => {
  const idx = draft.roles.indexOf(slug);
  if (idx === -1) draft.roles.push(slug);
  else draft.roles.splice(idx, 1);
};

const saving = ref(false);
const save = async () => {
  saving.value = true;
  try {
    const payload = {
      title: draft.title,
      message: draft.message,
      type: draft.type,
      roles: draft.roles,
      pages: draft.pages.split(',').map((p) => p.trim()).filter(Boolean),
      dismissible: draft.dismissible,
      active: draft.active,
    };
    if (isCreating.value) {
      const created = await api('/notices', { method: 'POST', body: JSON.stringify(payload) });
      await load();
      selectNotice(created);
    } else if (selectedId.value) {
      await api(`/notices/${selectedId.value}`, { method: 'PUT', body: JSON.stringify(payload) });
      await load();
    }
  } finally {
    saving.value = false;
  }
};

const remove = async () => {
  if (!selectedId.value) return;
  await api(`/notices/${selectedId.value}`, { method: 'DELETE' });
  selectedId.value = null;
  await load();
};
</script>

<template>
  <AppListShell
    v-model:search="search"
    title="Admin Notices"
    search-placeholder="Search notices…"
    detail-icon="dashicons-megaphone"
    detail-title="Notice Editor"
    detail-text="Select a notice from the list to edit it, or create a new one to get started."
    :has-selection="!!selected || isCreating"
  >
    <template #header-actions>
      <button type="button" @click="newNotice">+</button>
    </template>

    <template #detail-empty-actions>
      <button type="button" class="is-primary" style="margin-top: 14px;" @click="newNotice">
        Create Your First Notice
      </button>
    </template>

    <template #list>
      <AppListItem
        v-for="n in filtered"
        :key="n.id"
        icon="dashicons-megaphone"
        :title="n.title"
        :subtitle="n.active ? n.type : `${n.type} (inactive)`"
        :active="n.id === selectedId"
        @click="selectNotice(n)"
      />
    </template>

    <template #detail>
      <div class="notice-editor">
        <div class="notice-editor__row">
          <input v-model="draft.title" type="text" placeholder="Notice title" class="notice-editor__title" />
          <select v-model="draft.type">
            <option value="info">Info</option>
            <option value="success">Success</option>
            <option value="warning">Warning</option>
            <option value="error">Error</option>
          </select>
        </div>

        <textarea v-model="draft.message" placeholder="Message" rows="4" />

        <div class="notice-editor__section">
          <h3>Target roles (empty = all)</h3>
          <div class="notice-editor__roles">
            <label v-for="r in roleOptions" :key="r.value" class="notice-editor__role">
              <input type="checkbox" :checked="draft.roles.includes(r.value)" @change="toggleRole(r.value)" />
              {{ r.label }}
            </label>
          </div>
        </div>

        <div class="notice-editor__section">
          <h3>Target pages (comma-separated, e.g. index.php, edit.php — empty = all)</h3>
          <input v-model="draft.pages" type="text" placeholder="index.php, edit.php" />
        </div>

        <div class="notice-editor__toggles">
          <label><input type="checkbox" v-model="draft.dismissible" /> Dismissible</label>
          <label><input type="checkbox" v-model="draft.active" /> Active</label>
        </div>

        <div class="notice-editor__actions">
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
.notice-editor { display: flex; flex-direction: column; gap: 18px; }
.notice-editor__row { display: flex; gap: 10px; }
.notice-editor__title {
  flex: 1; font-size: 1.05rem; font-weight: 700; padding: 8px 10px;
  border: 1px solid var(--aurora-border); border-radius: var(--aurora-radius-sm);
  background: var(--aurora-bg-subtle); color: var(--aurora-text);
}
.notice-editor select, .notice-editor input[type='text'], .notice-editor textarea {
  padding: 8px 10px; border-radius: var(--aurora-radius-sm); border: 1px solid var(--aurora-border);
  background: var(--aurora-bg-subtle); color: var(--aurora-text); font-size: 0.8125rem;
  font-family: inherit; width: 100%; box-sizing: border-box;
}
.notice-editor__section h3 {
  font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;
  color: var(--aurora-text-muted); margin: 0 0 8px;
}
.notice-editor__roles { display: flex; flex-wrap: wrap; gap: 12px; }
.notice-editor__role { display: flex; align-items: center; gap: 6px; font-size: 0.8125rem; color: var(--aurora-text); }
.notice-editor__toggles { display: flex; gap: 20px; font-size: 0.8125rem; color: var(--aurora-text); }
.notice-editor__toggles label { display: flex; align-items: center; gap: 6px; }
.notice-editor__actions { display: flex; justify-content: flex-end; gap: 8px; }
.notice-editor__actions button {
  border: none; border-radius: var(--aurora-radius-sm); padding: 9px 18px; font-size: 0.875rem; cursor: pointer;
  background: var(--aurora-bg-subtle); color: var(--aurora-text);
}
.notice-editor__actions .is-primary { background: var(--aurora-accent); color: #fff; }
.notice-editor__actions .is-danger { color: #ef4444; }
.notice-editor__actions button:disabled { opacity: 0.5; cursor: default; }
</style>
