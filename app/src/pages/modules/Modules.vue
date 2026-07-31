<script setup>
import { ref, reactive, onMounted } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const api = (path, options = {}) =>
  fetch(`${props.serverData.restUrl}aurora-admin/v1${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': props.serverData.restNonce,
      ...(options.headers || {}),
    },
  }).then(async (r) => {
    const data = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(data.message || 'Request failed');
    return data;
  });

// Fetched from the server (manifest metadata + local install/active status
// merged), not a static list — a module with no manifest entry simply
// never appears here.
const modules = ref([]);
const loading = ref(true);
const loadError = ref('');

// Per-slug UI state: { busy, error }. Populated once modules load.
const state = reactive({});

const load = async () => {
  loading.value = true;
  loadError.value = '';
  try {
    modules.value = await api('/companion-plugins');
    for (const m of modules.value) {
      if (!state[m.slug]) state[m.slug] = { busy: false, error: '' };
    }
  } catch (e) {
    loadError.value = e.message || 'Could not check for available modules.';
  } finally {
    loading.value = false;
  }
};

onMounted(load);

const install = async (module) => {
  const s = state[module.slug];
  if (s.busy) return;
  s.busy = true;
  s.error = '';
  try {
    const result = await api(`/companion-plugins/${module.slug}/install`, { method: 'POST' });
    if (!result.success) {
      s.error = result.message || 'Installation failed.';
    }
    await load();
  } catch (e) {
    s.error = e.message || 'Installation failed.';
  } finally {
    s.busy = false;
  }
};
</script>

<template>
  <main class="modules">
    <header class="modules__head">
      <p class="modules__eyebrow">Aurora Admin</p>
      <h1>Modules</h1>
      <p class="modules__lead">
        Install additional modules to extend Aurora Admin. Each is a separate, standalone plugin —
        install and activate it here with one click.
      </p>
    </header>

    <p v-if="loading" class="modules__status">Checking for available modules…</p>
    <p v-else-if="loadError" class="modules__status modules__status--error">{{ loadError }}</p>
    <p v-else-if="modules.length === 0" class="modules__status">
      No modules are available right now. Check back later.
    </p>

    <div v-else class="modules__grid">
      <article v-for="m in modules" :key="m.slug" class="module-card">
        <div class="module-card__top">
          <span class="module-card__icon dashicons dashicons-admin-plugins" />
          <span class="module-card__badge" :class="{ 'is-active': m.active }">
            {{ m.active ? 'Active' : m.installed ? 'Installed' : 'Not installed' }}
          </span>
        </div>
        <h3 class="module-card__title">{{ m.name }}</h3>
        <p class="module-card__desc">{{ m.description }}</p>

        <div class="module-card__action">
          <a v-if="m.active" class="module-card__manage" :href="`admin.php?page=aurora-${m.slug}`">
            Open {{ m.name }} →
          </a>
          <template v-else>
            <button
              type="button"
              class="module-card__install"
              :disabled="state[m.slug]?.busy"
              @click="install(m)"
            >
              {{ state[m.slug]?.busy ? 'Installing…' : m.installed ? 'Activate' : 'Install & Activate' }}
            </button>
            <p v-if="state[m.slug]?.error" class="module-card__error">{{ state[m.slug].error }}</p>
          </template>
        </div>
      </article>
    </div>
  </main>
</template>

<style scoped>
.modules { min-height: calc(100vh - 112px); color: var(--aurora-text); background: transparent; }
.modules__eyebrow {
  margin: 0 0 7px; color: var(--aurora-text-muted); font-size: 0.72rem;
  font-weight: 800; text-transform: uppercase;
}
.modules__head h1 { margin: 0; font-size: 1.45rem; line-height: 1.15; }
.modules__lead { margin: 8px 0 0; color: var(--aurora-text-muted); font-size: 0.9rem; max-width: 640px; line-height: 1.6; }
.modules__status { margin-top: 24px; font-size: 0.875rem; color: var(--aurora-text-muted); }
.modules__status--error { color: #e5484d; }
.modules__grid {
  margin-top: 24px; display: grid; gap: 16px;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
}
.module-card {
  background: var(--aurora-card-bg); border: 1px solid var(--aurora-card-border);
  border-radius: var(--aurora-radius-lg); padding: 20px 22px;
  display: flex; flex-direction: column; gap: 8px;
}
.module-card__top { display: flex; align-items: center; justify-content: space-between; }
.module-card__icon { font-size: 26px; width: 26px; height: 26px; color: var(--aurora-accent); }
.module-card__badge {
  font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;
  color: var(--aurora-text-muted); background: var(--aurora-bg-subtle);
  border-radius: 999px; padding: 3px 10px;
}
.module-card__badge.is-active { color: var(--aurora-accent); }
.module-card__title { margin: 4px 0 0; font-size: 1rem; font-weight: 700; }
.module-card__desc { margin: 0; font-size: 0.8125rem; color: var(--aurora-text-muted); line-height: 1.55; flex: 1; }
.module-card__action { margin-top: 6px; }
.module-card__manage { font-size: 0.8125rem; font-weight: 600; color: var(--aurora-accent); text-decoration: none; }
.module-card__manage:hover { text-decoration: underline; }
.module-card__install {
  border: none; border-radius: var(--aurora-radius-sm); padding: 9px 16px; font-size: 0.8125rem;
  font-weight: 600; cursor: pointer; background: var(--aurora-accent); color: #fff;
}
.module-card__install:disabled { opacity: 0.6; cursor: default; }
.module-card__error { margin: 8px 0 0; font-size: 0.8125rem; color: #e5484d; }
</style>
