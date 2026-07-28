<script setup>
import { reactive } from 'vue';

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

// Local, mutable copy so a row's status/action can update in place once
// installed/activated, without needing a full page reload.
const importers = reactive(props.serverData.importers.map((i) => ({ ...i, busy: false, error: '' })));

const goToImporter = (importer) => {
  window.location.href = importer.runUrl;
};

const activate = async (importer) => {
  importer.busy = true;
  importer.error = '';
  try {
    await api('/plugins/activate', {
      method: 'POST',
      body: JSON.stringify({ file: importer.pluginFile }),
    });
    goToImporter(importer);
  } catch (e) {
    importer.error = e.message || 'Could not activate the importer.';
  } finally {
    importer.busy = false;
  }
};

const installAndActivate = async (importer) => {
  importer.busy = true;
  importer.error = '';
  try {
    // Reuses WordPress core's own admin-ajax "install-plugin" action — the
    // exact same request native's "Install Now" button triggers — rather
    // than reimplementing plugin installation.
    const body = new URLSearchParams({
      action: 'install-plugin',
      slug: importer.slug,
      _ajax_nonce: props.serverData.installNonce,
    });
    const res = await fetch(props.serverData.ajaxUrl, { method: 'POST', body });
    const data = await res.json().catch(() => ({}));
    if (!data.success) {
      throw new Error(data.data?.errorMessage || 'Installation failed.');
    }

    // Find the freshly-installed plugin's file path (rather than assuming a
    // naming convention) via Aurora's own plugins list, then activate it
    // through the same route the Plugins screen uses.
    const list = await api('/plugins');
    const installed = (Array.isArray(list) ? list : []).find((p) => p.slug === importer.slug);
    if (!installed) {
      throw new Error('Installed, but the plugin could not be found afterward.');
    }
    await api('/plugins/activate', {
      method: 'POST',
      body: JSON.stringify({ file: installed.file }),
    });
    goToImporter(importer);
  } catch (e) {
    importer.error = e.message || 'Could not install the importer.';
  } finally {
    importer.busy = false;
  }
};
</script>

<template>
  <main class="import-page">
    <header class="import-page__header">
      <p class="import-page__eyebrow">Aurora Admin</p>
      <h1>Import</h1>
      <p class="import-page__lead">
        If you have posts or comments in another system, WordPress can import those into this
        site. Choose a system to import from below.
      </p>
    </header>

    <div class="import-page__list">
      <article v-for="imp in importers" :key="imp.id" class="import-card">
        <div class="import-card__info">
          <h3>{{ imp.name }}</h3>
          <p>{{ imp.description }}</p>
          <p v-if="imp.error" class="import-card__error">{{ imp.error }}</p>
        </div>
        <div class="import-card__action">
          <a v-if="imp.status === 'active'" class="import-card__btn" @click.prevent="goToImporter(imp)" href="#">
            Run Importer
          </a>
          <button
            v-else-if="imp.status === 'installed_inactive'"
            type="button"
            class="import-card__btn"
            :disabled="imp.busy"
            @click="activate(imp)"
          >
            {{ imp.busy ? 'Activating…' : 'Run Importer' }}
          </button>
          <button
            v-else
            type="button"
            class="import-card__btn"
            :disabled="imp.busy"
            @click="installAndActivate(imp)"
          >
            {{ imp.busy ? 'Installing…' : 'Install Now' }}
          </button>
        </div>
      </article>

      <p v-if="!importers.length" class="import-page__empty">No importers are available.</p>
    </div>

    <p v-if="serverData.canInstallPlugins" class="import-page__more">
      If the importer you need is not listed,
      <a :href="serverData.pluginSearchUrl">search the plugin directory</a>
      to see if an importer is available.
    </p>

    <!-- Rendered from WordPress's own `import_filters` hook — whatever other
         active plugins add there. Not Aurora-authored content, only
         Aurora-styled, so nothing another plugin adds here disappears. -->
    <div v-if="serverData.nativeFiltersHtml" class="import-page__native" v-html="serverData.nativeFiltersHtml" />
  </main>
</template>

<style scoped>
.import-page { min-height: calc(100vh - 112px); color: var(--aurora-text); background: transparent; }
.import-page__eyebrow {
  margin: 0 0 7px; color: var(--aurora-text-muted); font-size: 0.72rem;
  font-weight: 800; text-transform: uppercase;
}
.import-page__header h1 { margin: 0; font-size: 1.45rem; line-height: 1.15; }
.import-page__lead {
  margin: 12px 0 0; max-width: 640px; color: var(--aurora-text-muted);
  font-size: 0.875rem; line-height: 1.6;
}

.import-page__list { margin-top: 24px; display: flex; flex-direction: column; gap: 10px; max-width: 720px; }
.import-card {
  display: flex; align-items: center; justify-content: space-between; gap: 20px;
  background: var(--aurora-card-bg); border: 1px solid var(--aurora-card-border);
  border-radius: var(--aurora-radius-lg); padding: 16px 20px;
}
.import-card__info h3 { margin: 0 0 4px; font-size: 0.9375rem; font-weight: 700; }
.import-card__info p { margin: 0; font-size: 0.8125rem; color: var(--aurora-text-muted); line-height: 1.5; }
.import-card__error { color: #e5484d !important; margin-top: 6px !important; }
.import-card__action { flex-shrink: 0; }
.import-card__btn {
  display: inline-block; border: none; border-radius: var(--aurora-radius-sm); padding: 8px 16px;
  font-size: 0.8125rem; font-weight: 600; cursor: pointer; background: var(--aurora-accent);
  color: #fff; text-decoration: none; white-space: nowrap;
}
.import-card__btn:disabled { opacity: 0.6; cursor: default; }

.import-page__empty { color: var(--aurora-text-muted); font-size: 0.875rem; }
.import-page__more { margin-top: 18px; font-size: 0.8125rem; color: var(--aurora-text-muted); }
.import-page__more a { color: var(--aurora-accent); }

.import-page__native { margin-top: 18px; }
</style>
