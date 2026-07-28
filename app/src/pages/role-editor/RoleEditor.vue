<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import AppListShell from '../_shared/AppListShell.vue';
import AppListItem from '../_shared/AppListItem.vue';
import { describeCapability } from './capabilityDescriptions.js';

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

const roles = ref([]);
const allCapabilities = ref([]);
const search = ref('');
const selectedSlug = ref(null);
const isCreating = ref(false);
const errorMsg = ref('');

const load = async () => {
  const data = await api('/roles');
  roles.value = data.roles;
  allCapabilities.value = data.allCapabilities;
};

onMounted(load);

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return roles.value;
  return roles.value.filter((r) => r.name.toLowerCase().includes(q));
});

const selected = computed(() => roles.value.find((r) => r.slug === selectedSlug.value) || null);

const draft = reactive({ slug: '', name: '', capabilities: [] });

const selectRole = (r) => {
  selectedSlug.value = r.slug;
  isCreating.value = false;
  errorMsg.value = '';
  Object.assign(draft, { slug: r.slug, name: r.name, capabilities: [...r.capabilities] });
};

const newRole = () => {
  selectedSlug.value = null;
  isCreating.value = true;
  errorMsg.value = '';
  Object.assign(draft, { slug: '', name: '', capabilities: ['read'] });
};

const toggleCap = (cap) => {
  const idx = draft.capabilities.indexOf(cap);
  if (idx === -1) draft.capabilities.push(cap);
  else draft.capabilities.splice(idx, 1);
};

const saving = ref(false);
const save = async () => {
  saving.value = true;
  errorMsg.value = '';
  try {
    if (isCreating.value) {
      await api('/roles', {
        method: 'POST',
        body: JSON.stringify({ slug: draft.slug, displayName: draft.name, capabilities: draft.capabilities }),
      });
      await load();
      isCreating.value = false;
      selectRole(roles.value.find((r) => r.slug === draft.slug));
    } else if (selectedSlug.value) {
      await api(`/roles/${selectedSlug.value}`, {
        method: 'PUT',
        body: JSON.stringify({ displayName: draft.name, capabilities: draft.capabilities }),
      });
      await load();
    }
  } catch (e) {
    errorMsg.value = 'Could not save — check the role slug is unique.';
  } finally {
    saving.value = false;
  }
};

const remove = async () => {
  if (!selectedSlug.value || selected.value?.isDefault) return;
  await api(`/roles/${selectedSlug.value}`, { method: 'DELETE' });
  selectedSlug.value = null;
  await load();
};
</script>

<template>
  <AppListShell
    v-model:search="search"
    title="Role Editor"
    search-placeholder="Search roles…"
    :count-label="`${filtered.length} ROLES`"
    detail-icon="dashicons-groups"
    detail-title="Role Details"
    detail-text="Select a role from the list to view and edit its capabilities."
    :has-selection="!!selected || isCreating"
  >
    <template #header-actions>
      <button type="button" class="is-primary" @click="newRole">+ New Role</button>
    </template>

    <template #list>
      <AppListItem
        v-for="r in filtered"
        :key="r.slug"
        icon="dashicons-groups"
        :title="r.name"
        :subtitle="`${r.userCount} user${r.userCount === 1 ? '' : 's'}`"
        :active="r.slug === selectedSlug"
        @click="selectRole(r)"
      />
    </template>

    <template #detail>
      <div class="role-editor">
        <div class="role-editor__row">
          <input
            v-if="isCreating"
            v-model="draft.slug"
            type="text"
            placeholder="role_slug"
            class="role-editor__slug"
          />
          <input v-model="draft.name" type="text" placeholder="Display name" class="role-editor__name" />
        </div>

        <p v-if="errorMsg" class="role-editor__error">{{ errorMsg }}</p>

        <div class="role-editor__section">
          <h3>Capabilities</h3>
          <div class="role-editor__caps">
            <div v-for="cap in allCapabilities" :key="cap" class="role-editor__cap">
              <label>
                <input type="checkbox" :checked="draft.capabilities.includes(cap)" @change="toggleCap(cap)" />
                {{ cap }}
              </label>
              <span class="role-editor__cap-info" tabindex="0">
                <span class="dashicons dashicons-info-outline" />
                <span class="role-editor__tooltip" role="tooltip">{{ describeCapability(cap) }}</span>
              </span>
            </div>
          </div>
        </div>

        <div class="role-editor__actions">
          <button
            type="button"
            class="is-danger"
            :disabled="!selectedSlug || selected?.isDefault"
            :title="selected?.isDefault ? 'Default WordPress roles cannot be deleted' : ''"
            @click="remove"
          >
            Delete
          </button>
          <button type="button" class="is-primary" :disabled="saving" @click="save">
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </div>
    </template>
  </AppListShell>
</template>

<style scoped>
.role-editor { display: flex; flex-direction: column; gap: 18px; }
.role-editor__row { display: flex; gap: 10px; }
.role-editor__slug, .role-editor__name {
  padding: 8px 10px; border-radius: var(--aurora-radius-sm); border: 1px solid var(--aurora-border);
  background: var(--aurora-bg-subtle); color: var(--aurora-text); font-size: 0.875rem;
}
.role-editor__name { flex: 1; font-weight: 700; }
.role-editor__slug { width: 160px; }
.role-editor__error { color: #ef4444; font-size: 0.8125rem; margin: 0; }
.role-editor__section h3 {
  font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;
  color: var(--aurora-text-muted); margin: 0 0 8px;
}
.role-editor__caps {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 8px 16px; max-height: 380px; overflow-y: auto;
  /* Tooltips (position: absolute, below) need room to render outside the
     grid cell without getting clipped by the scroll container. */
  padding-bottom: 8px;
}
.role-editor__cap {
  display: flex; align-items: center; justify-content: space-between; gap: 6px;
  font-size: 0.8125rem; color: var(--aurora-text);
}
.role-editor__cap label { display: flex; align-items: center; gap: 6px; min-width: 0; }
.role-editor__cap label span,
.role-editor__cap label {
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

.role-editor__cap-info {
  position: relative;
  display: inline-flex; align-items: center; justify-content: center;
  width: 18px; height: 18px; flex-shrink: 0;
  color: var(--aurora-text-muted); cursor: help;
}
.role-editor__cap-info .dashicons { font-size: 15px; width: 15px; height: 15px; }
.role-editor__cap-info:hover { color: var(--aurora-accent); }

.role-editor__tooltip {
  position: absolute;
  bottom: calc(100% + 8px);
  right: -8px;
  z-index: 20;
  width: 260px;
  padding: 10px 12px;
  border-radius: var(--aurora-radius-sm);
  border: 1px solid var(--aurora-frame-border);
  background: var(--aurora-frame-bg);
  color: var(--aurora-text);
  font-size: 0.75rem;
  font-weight: 400;
  line-height: 1.5;
  white-space: normal;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
  visibility: hidden;
  opacity: 0;
  transition: opacity 0.1s ease;
  pointer-events: none;
}
.role-editor__cap-info:hover .role-editor__tooltip,
.role-editor__cap-info:focus .role-editor__tooltip {
  visibility: visible;
  opacity: 1;
}
.role-editor__actions { display: flex; justify-content: flex-end; gap: 8px; }
.role-editor__actions button {
  border: none; border-radius: var(--aurora-radius-sm); padding: 9px 18px; font-size: 0.875rem; cursor: pointer;
  background: var(--aurora-bg-subtle); color: var(--aurora-text);
}
.role-editor__actions .is-primary { background: var(--aurora-accent); color: #fff; }
.role-editor__actions .is-danger { color: #ef4444; }
.role-editor__actions button:disabled { opacity: 0.5; cursor: default; }
</style>
