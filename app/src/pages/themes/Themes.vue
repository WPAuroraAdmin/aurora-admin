<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const api = (path) => `${props.serverData.restUrl}aurora-admin/v1${path}`;
const headers = () => ({ 'Content-Type': 'application/json', 'X-WP-Nonce': props.serverData.restNonce });

const themes = ref([]);
const loading = ref(true);
const error = ref('');
const busy = ref('');

const load = async () => {
  loading.value = true;
  try {
    const res = await fetch(api('/themes'), { headers: headers() });
    const data = await res.json();
    if (!res.ok) throw new Error(data?.message || 'Could not load themes.');
    themes.value = data;
  } catch (e) {
    error.value = e.message || 'Could not load themes.';
  } finally {
    loading.value = false;
  }
};

onMounted(load);

const activate = async (theme) => {
  busy.value = theme.stylesheet;
  try {
    const res = await fetch(api('/themes/activate'), {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify({ stylesheet: theme.stylesheet }),
    });
    if (!res.ok) throw new Error('Could not activate theme.');
    await load();
  } catch (e) {
    error.value = e.message;
  } finally {
    busy.value = '';
  }
};

const remove = async (theme) => {
  if (!confirm(`Delete "${theme.name}"? This can't be undone.`)) return;
  busy.value = theme.stylesheet;
  try {
    const res = await fetch(api(`/themes/${encodeURIComponent(theme.stylesheet)}`), {
      method: 'DELETE',
      headers: headers(),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data?.message || 'Could not delete theme.');
    await load();
  } catch (e) {
    error.value = e.message;
    setTimeout(() => (error.value = ''), 3000);
  } finally {
    busy.value = '';
  }
};
</script>

<template>
  <main class="themes-page">
    <header class="themes-page__header">
      <div>
        <p class="themes-page__eyebrow">Appearance</p>
        <h1>Themes</h1>
      </div>
      <div class="themes-page__actions">
        <a v-if="serverData.addThemeUrl" class="button-primary" :href="serverData.addThemeUrl">
          Add New Theme
        </a>
        <a class="button" :href="serverData.customizeUrl">Customize</a>
      </div>
    </header>

    <div v-if="error" class="themes-notice themes-notice--error">{{ error }}</div>
    <p v-if="loading" class="themes-empty">Loading…</p>

    <div v-else class="themes-grid">
      <article v-for="theme in themes" :key="theme.stylesheet" class="theme-card" :class="{ 'theme-card--active': theme.active }">
        <div class="theme-card__shot">
          <img v-if="theme.screenshot" :src="theme.screenshot" :alt="theme.name" />
          <span v-else class="dashicons dashicons-admin-appearance" />
        </div>
        <div class="theme-card__body">
          <h3>{{ theme.name }} <span>{{ theme.version }}</span></h3>
          <p>{{ theme.description }}</p>
        </div>
        <div class="theme-card__actions">
          <span v-if="theme.active" class="theme-card__badge">Active</span>
          <button
            v-else
            type="button"
            class="button-primary"
            :disabled="busy === theme.stylesheet"
            @click="activate(theme)"
          >
            {{ busy === theme.stylesheet ? 'Activating…' : 'Activate' }}
          </button>
          <button
            v-if="!theme.active"
            type="button"
            class="icon-button"
            title="Delete theme"
            :disabled="busy === theme.stylesheet"
            @click="remove(theme)"
          >
            <span class="dashicons dashicons-trash" />
          </button>
        </div>
      </article>
    </div>
  </main>
</template>

<style scoped>
.themes-page { max-width: 1100px; margin: 0 auto; }
.themes-page__header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.themes-page__actions { display: flex; align-items: center; gap: 8px; }
.themes-page__eyebrow {
  margin: 0 0 4px; font-size: 0.75rem; text-transform: uppercase;
  letter-spacing: 0.06em; color: var(--aurora-text-muted);
}
.themes-page h1 { margin: 0; font-size: 1.6rem; font-weight: 700; color: var(--aurora-text); }
.themes-empty { color: var(--aurora-text-muted); font-size: 0.8125rem; }
.themes-notice { padding: 10px 14px; border-radius: var(--aurora-radius-sm); margin-bottom: 16px; font-size: 0.875rem; }
.themes-notice--error { background: rgba(239, 68, 68, 0.12); color: #ef4444; }

.themes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
.theme-card {
  background: var(--aurora-bg-subtle); border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-md); overflow: hidden; display: flex; flex-direction: column;
}
.theme-card--active { border-color: var(--aurora-accent); }
.theme-card__shot {
  aspect-ratio: 4 / 3; background: var(--aurora-bg);
  display: flex; align-items: center; justify-content: center;
  color: var(--aurora-text-muted);
}
.theme-card__shot img { width: 100%; height: 100%; object-fit: cover; }
.theme-card__shot .dashicons { font-size: 40px; width: 40px; height: 40px; }
.theme-card__body { padding: 14px 16px 8px; flex: 1; }
.theme-card__body h3 { margin: 0 0 6px; font-size: 0.9375rem; color: var(--aurora-text); }
.theme-card__body h3 span { font-weight: 400; font-size: 0.75rem; color: var(--aurora-text-muted); margin-left: 6px; }
.theme-card__body p {
  margin: 0; font-size: 0.8125rem; color: var(--aurora-text-muted);
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.theme-card__actions { display: flex; align-items: center; gap: 8px; padding: 12px 16px 16px; }
.theme-card__badge {
  font-size: 0.75rem; font-weight: 600; color: var(--aurora-accent);
  background: var(--aurora-accent-soft); padding: 4px 10px; border-radius: 999px;
}
</style>
