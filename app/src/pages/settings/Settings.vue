<script setup>
import { ref, reactive, computed, watch, nextTick } from 'vue';
import { settingsGroups, settingsCategories } from './settingsConfig.js';
import SettingsField from './components/SettingsField.vue';
import { applyThemePalette, applyFontFamily } from './applyLiveTheme.js';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const visibleCategories = settingsCategories;

// Working copy of the settings, seeded from the server + field defaults.
const settings = reactive({ ...(props.serverData.settings || {}) });
visibleCategories.forEach((cat) =>
  cat.fields.forEach((f) => {
    if (settings[f.key] === undefined && f.default !== undefined) {
      settings[f.key] = f.default;
    }
  })
);

// Active category, synced with the URL ?category= param.
const initialCat = new URLSearchParams(window.location.search).get('category');
const activeId = ref(
  visibleCategories.some((c) => c.id === initialCat) ? initialCat : visibleCategories[0].id
);
const activeCategory = computed(
  () => visibleCategories.find((c) => c.id === activeId.value) || visibleCategories[0]
);

const groupOfCategory = (catId) =>
  settingsGroups.find((g) => g.categories.some((c) => c.id === catId));

// Collapsible nav groups. Default open; collapse state persisted per group.
const OPEN_KEY = 'aurora-settings-groups-open';
const loadOpen = () => {
  try {
    return JSON.parse(localStorage.getItem(OPEN_KEY)) || {};
  } catch {
    return {};
  }
};
const openState = reactive(loadOpen());
// Keep the active category's group open so its item is always reachable.
const activeGroup = groupOfCategory(activeId.value);
if (activeGroup) openState[activeGroup.id] = true;

const isGroupOpen = (id) => openState[id] !== false;
const toggleGroup = (id) => {
  openState[id] = !isGroupOpen(id);
  localStorage.setItem(OPEN_KEY, JSON.stringify(openState));
};

// The content panel scrolls independently of the nav (see .settings__content
// below) so picking a category from a scrolled-down spot in the nav doesn't
// require scrolling the whole page back up to see it. But the panel is one
// persistent DOM node across category switches — its scrollTop doesn't reset
// on its own unless the new content happens to be shorter, so without this
// a long category could inherit a scrolled-down position from the previous
// one and land on the same "content is off-screen" problem this was meant
// to fix.
const contentEl = ref(null);
const selectCategory = (id) => {
  activeId.value = id;
  const g = groupOfCategory(id);
  if (g) openState[g.id] = true;
  const url = new URL(window.location.href);
  url.searchParams.set('category', id);
  window.history.replaceState({}, '', url);
  nextTick(() => {
    if (contentEl.value) contentEl.value.scrollTop = 0;
  });
};

// Search filter over the nav — matches on group title, category title, or
// subtitle; groups with no surviving categories drop out entirely.
const query = ref('');
const searching = computed(() => query.value.trim().length > 0);
const filteredGroups = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return settingsGroups;
  return settingsGroups
    .map((g) => {
      const groupMatch = g.title.toLowerCase().includes(q);
      const categories = g.categories.filter(
        (c) =>
          groupMatch ||
          c.title.toLowerCase().includes(q) ||
          c.subtitle.toLowerCase().includes(q)
      );
      return { ...g, categories };
    })
    .filter((g) => g.categories.length > 0);
});

// Options for multiselect fields, resolved from server-provided lists.
const optionsFor = (field) => {
  if (field.optionsSource === 'roles') return props.serverData.roles || [];
  if (field.optionsSource === 'postTypes') return props.serverData.postTypes || [];
  return field.options || [];
};

// Live preview — applied as soon as a picker changes, not just after
// Save + reload.
watch(
  () => settings.theme_preset,
  (preset) => applyThemePalette(preset || 'indigo'),
  { immediate: true }
);
watch(
  () => settings.font_family,
  (family) => applyFontFamily(family),
  { immediate: true }
);

// Save -------------------------------------------------------------------
const saving = ref(false);
const savedMsg = ref('');
const save = async () => {
  saving.value = true;
  savedMsg.value = '';
  try {
    const res = await fetch(`${props.serverData.restUrl}wp/v2/settings`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': props.serverData.restNonce },
      body: JSON.stringify({ aurora_admin_settings: { ...settings } }),
    });
    if (!res.ok) throw new Error();
    savedMsg.value = 'Saved';
    setTimeout(() => (savedMsg.value = ''), 2500);
  } catch {
    savedMsg.value = 'Could not save';
  } finally {
    saving.value = false;
  }
};

// Import / export --------------------------------------------------------
const exportSettings = () => {
  const blob = new Blob([JSON.stringify(settings, null, 2)], { type: 'application/json' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'aurora-admin-settings.json';
  a.click();
  URL.revokeObjectURL(a.href);
};
const importInput = ref(null);
const importSettings = (e) => {
  const file = e.target.files?.[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = () => {
    try {
      const obj = JSON.parse(reader.result);
      Object.assign(settings, obj);
    } catch {
      savedMsg.value = 'Invalid file';
    }
  };
  reader.readAsText(file);
  e.target.value = '';
};
</script>

<template>
  <div class="settings">
    <!-- Left nav panel -->
    <aside class="settings__nav">
      <div class="settings__nav-head">
        <h1 class="settings__title">Settings</h1>
        <div class="settings__actions">
          <button type="button" title="Export settings" @click="exportSettings">
            <span class="dashicons dashicons-download" />
          </button>
          <button type="button" title="Import settings" @click="importInput.click()">
            <span class="dashicons dashicons-upload" />
          </button>
          <input
            ref="importInput"
            type="file"
            accept="application/json"
            class="settings__file"
            @change="importSettings"
          />
        </div>
      </div>

      <div class="settings__search">
        <span class="dashicons dashicons-search" />
        <input v-model="query" type="text" placeholder="Search settings…" />
      </div>

      <ul class="settings__groups">
        <li v-for="group in filteredGroups" :key="group.id" class="settings__group">
          <button
            type="button"
            class="settings__group-head"
            :aria-expanded="isGroupOpen(group.id) || searching"
            @click="toggleGroup(group.id)"
          >
            <span class="settings__group-icon dashicons" :class="group.icon" />
            <span class="settings__group-title">{{ group.title }}</span>
            <span
              class="settings__group-caret dashicons dashicons-arrow-down-alt2"
              :class="{ 'is-collapsed': !isGroupOpen(group.id) && !searching }"
            />
          </button>
          <ul v-show="isGroupOpen(group.id) || searching" class="settings__list">
            <li v-for="cat in group.categories" :key="cat.id">
              <button
                type="button"
                class="settings__cat"
                :class="{ 'settings__cat--active': cat.id === activeId }"
                @click="selectCategory(cat.id)"
              >
                <span class="settings__cat-icon dashicons" :class="cat.icon" />
                <span class="settings__cat-title">{{ cat.title }}</span>
              </button>
            </li>
          </ul>
        </li>
        <li v-if="!filteredGroups.length" class="settings__empty">
          No settings match “{{ query }}”.
        </li>
      </ul>
    </aside>

    <!-- Right content panel -->
    <section ref="contentEl" class="settings__content">
      <header class="settings__content-head">
        <div>
          <h2 class="settings__content-title">{{ activeCategory.title }}</h2>
          <p class="settings__content-sub">{{ activeCategory.subtitle }}</p>
        </div>
        <div class="settings__save-wrap">
          <span v-if="savedMsg" class="settings__saved">{{ savedMsg }}</span>
          <button type="button" class="settings__save" :disabled="saving" @click="save">
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </header>

      <div class="settings__fields">
        <SettingsField
          v-for="f in activeCategory.fields"
          :key="f.key"
          :field="f"
          :options="optionsFor(f)"
          :disabled="!!f.dependsOn && !settings[f.dependsOn]"
          v-model="settings[f.key]"
        />
      </div>
    </section>
  </div>
</template>

<style scoped>
.settings {
  display: flex;
  align-items: flex-start;
  gap: 20px;
  /* height, not min-height — the outer #wpbody-content card (shell-
     frame.css) already caps itself to the viewport and scrolls as a
     whole. Filling it exactly (rather than growing taller than it) is
     what lets .settings__nav and .settings__content become their own
     independent scroll regions below instead of both scrolling together
     as one long page. */
  height: calc(100vh - var(--aurora-toolbar-h) - 60px);
  margin: -32px; /* cancel the frame padding */
  padding: 22px; /* inset the two boxes; the frame's right gutter adds the rest */
  box-sizing: border-box;
  overflow: hidden;
  /* No background here — html/body already paint the page gradient
     (shell-frame.css); repainting the same var(--aurora-app-bg) here was
     just a redundant duplicate layer on top of it. */
}

/* Left nav — no background/border of its own; the page gradient shows
   straight through so it reads as one surface instead of a boxed panel.
   Scrolls independently of the content panel (own height + overflow)
   so its scroll position is preserved when you switch categories. */
.settings__nav {
  width: 340px;
  flex-shrink: 0;
  height: 100%;
  overflow-y: auto;
  border-radius: var(--aurora-radius-lg);
  padding: 24px 20px;
  box-sizing: border-box;
}
.settings__nav-head {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 18px;
}
.settings__title { font-size: 1.35rem; font-weight: 700; margin: 0; color: var(--aurora-text); }
.settings__actions { display: flex; gap: 4px; }
.settings__actions button {
  width: 30px; height: 30px; border: none; background: none;
  color: var(--aurora-text-muted); cursor: pointer; border-radius: var(--aurora-radius-sm);
  display: inline-flex; align-items: center; justify-content: center;
}
.settings__actions button:hover { background: var(--aurora-bg-subtle); color: var(--aurora-text); }
.settings__file { display: none; }

.settings__search {
  display: flex; align-items: center; gap: 8px;
  background: var(--aurora-bg-subtle);
  border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-md); padding: 8px 12px; margin-bottom: 16px;
  color: var(--aurora-text-muted);
}
.settings__search input {
  border: none; background: none; outline: none; color: var(--aurora-text);
  font-size: 0.8125rem; width: 100%;
}

.settings__groups { list-style: none; margin: 0; padding: 0; }
.settings__group { margin-bottom: 4px; }

.settings__group-head {
  display: flex; align-items: center; gap: 8px; width: 100%;
  padding: 6px 8px; border: none; background: none; cursor: pointer;
  border-radius: var(--aurora-radius-sm); color: var(--aurora-text-muted);
}
.settings__group-head:hover { color: var(--aurora-text); }
.settings__group-icon { font-size: 15px; width: 16px; height: 16px; }
.settings__group-title {
  font-size: 0.6875rem; font-weight: 600; letter-spacing: 0.04em;
  text-transform: uppercase;
}
.settings__group-caret {
  margin-left: auto; font-size: 16px; width: 16px; height: 16px;
  transition: transform 0.15s ease;
}
.settings__group-caret.is-collapsed { transform: rotate(-90deg); }

/* Indent the category items so they read as a submenu nested under their
   group header, rather than sitting flush at the same level. */
.settings__list { list-style: none; margin: 2px 0 8px; padding: 0 0 0 14px; }
.settings__cat {
  display: flex; align-items: center; gap: 10px; width: 100%;
  padding: 8px 10px; border: none; background: none; cursor: pointer;
  border-radius: var(--aurora-radius-md); text-align: left; margin-bottom: 1px;
  color: var(--aurora-text);
}
.settings__cat:hover { background: var(--aurora-bg-subtle); }
.settings__cat--active { background: var(--aurora-accent-soft); }
.settings__cat-icon {
  font-size: 16px; width: 18px; height: 18px; flex-shrink: 0;
  color: var(--aurora-text-muted);
}
.settings__cat--active .settings__cat-icon { color: var(--aurora-accent); }
.settings__cat-title { font-size: 0.8125rem; font-weight: 500; }
.settings__cat--active .settings__cat-title { color: var(--aurora-accent); font-weight: 600; }

.settings__empty {
  padding: 12px 10px; font-size: 0.8125rem; color: var(--aurora-text-muted);
}

/* Right content — no background/border of its own, same reasoning.
   Own height + overflow (independent of .settings__nav's) is the actual
   fix: clicking a category always shows its content from the top,
   whatever the nav's own scroll position is — resetScrollTop on
   category switch (see selectCategory() above) handles the case where
   this panel itself was already scrolled down. */
.settings__content {
  flex: 1; min-width: 0;
  height: 100%;
  overflow-y: auto;
  border-radius: var(--aurora-radius-lg);
  padding: 28px 32px;
  box-sizing: border-box;
}
.settings__content-head {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 24px;
  padding-bottom: 22px;
  margin-bottom: 4px;
  border-bottom: 1px solid var(--aurora-frame-border);
}
.settings__content-title { font-size: 1.6rem; font-weight: 700; margin: 0; color: var(--aurora-text); }
.settings__content-sub { margin: 6px 0 0; font-size: 0.875rem; color: var(--aurora-text-muted); }
.settings__save-wrap { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.settings__saved { font-size: 0.8125rem; color: var(--aurora-text-muted); }
.settings__save {
  background: var(--aurora-accent); color: #fff; border: none;
  border-radius: var(--aurora-radius-md); padding: 9px 20px; font-size: 0.875rem; font-weight: 500; cursor: pointer;
}
.settings__save:disabled { opacity: 0.6; cursor: default; }

.settings__fields > :last-child { border-bottom: none; }

@media (max-width: 1000px) {
  /* Independent-scroll panes only make sense side-by-side — stacked on a
     narrow screen, revert to one natural-height column so the page
     scrolls as a whole the normal way, same as before this change. */
  .settings { flex-direction: column; height: auto; overflow: visible; }
  .settings__nav { width: auto; height: auto; overflow: visible; }
  .settings__content { height: auto; overflow: visible; }
}
</style>
