<script setup>
import { reactive, onMounted } from 'vue';
import { companionPlugins } from './companionPlugins.js';

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

// Per-plugin UI state, keyed by slug: { status: {installed,active}|null, busy, error }
const state = reactive(
  Object.fromEntries(companionPlugins.map((p) => [p.slug, { status: null, busy: false, error: '' }]))
);

const loadStatus = async (plugin) => {
  try {
    state[plugin.slug].status = await api(plugin.statusEndpoint);
  } catch {
    state[plugin.slug].status = { installed: false, active: false };
  }
};

onMounted(() => {
  companionPlugins.forEach(loadStatus);
});

const install = async (plugin) => {
  const s = state[plugin.slug];
  if (s.busy) return;
  s.busy = true;
  s.error = '';
  try {
    const result = await api(plugin.installEndpoint, { method: 'POST' });
    if (!result.success) {
      s.error = result.message || 'Installation failed.';
    }
    await loadStatus(plugin);
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

    <div class="modules__grid">
      <article v-for="p in companionPlugins" :key="p.slug" class="module-card">
        <div class="module-card__top">
          <span class="module-card__icon dashicons" :class="p.icon" />
          <span
            v-if="state[p.slug].status"
            class="module-card__badge"
            :class="{ 'is-active': state[p.slug].status.active }"
          >
            {{ state[p.slug].status.active ? 'Active' : state[p.slug].status.installed ? 'Installed' : 'Not installed' }}
          </span>
        </div>
        <h3 class="module-card__title">{{ p.label }}</h3>
        <p class="module-card__desc">{{ p.description }}</p>

        <div class="module-card__action">
          <div v-if="!state[p.slug].status" class="module-card__checking">Checking…</div>
          <a v-else-if="state[p.slug].status.active" class="module-card__manage" :href="p.manageUrl">
            Open {{ p.label }} →
          </a>
          <template v-else>
            <button
              type="button"
              class="module-card__install"
              :disabled="state[p.slug].busy"
              @click="install(p)"
            >
              {{ state[p.slug].busy ? 'Installing…' : state[p.slug].status.installed ? 'Activate' : 'Install & Activate' }}
            </button>
            <p v-if="state[p.slug].error" class="module-card__error">{{ state[p.slug].error }}</p>
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
.module-card__checking { font-size: 0.8125rem; color: var(--aurora-text-muted); }
.module-card__manage { font-size: 0.8125rem; font-weight: 600; color: var(--aurora-accent); text-decoration: none; }
.module-card__manage:hover { text-decoration: underline; }
.module-card__install {
  border: none; border-radius: var(--aurora-radius-sm); padding: 9px 16px; font-size: 0.8125rem;
  font-weight: 600; cursor: pointer; background: var(--aurora-accent); color: #fff;
}
.module-card__install:disabled { opacity: 0.6; cursor: default; }
.module-card__error { margin: 8px 0 0; font-size: 0.8125rem; color: #e5484d; }
</style>
