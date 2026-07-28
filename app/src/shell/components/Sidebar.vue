<script setup>
import { ref, computed } from 'vue';
import UserMenu from './UserMenu.vue';

const props = defineProps({
  menu: {
    type: Array,
    default: () => [],
  },
  currentUrl: {
    type: String,
    default: '',
  },
  activeParentFile: {
    type: String,
    default: '',
  },
  logo: {
    type: String,
    default: '',
  },
  darkLogo: {
    type: String,
    default: '',
  },
  collapsed: {
    type: Boolean,
    default: false,
  },
  auroraNav: {
    type: Array,
    default: () => [],
  },
  serverData: {
    type: Object,
    required: true,
  },
});

// Custom logo from Settings > General. Every theme preset is a dark-toned
// palette now (no separate light mode), so the dark-variant logo is always
// preferred when set; falls back to the standard logo, or the "Aurora"
// wordmark if neither is set.
const activeLogo = computed(() => props.darkLogo || props.logo);

// Normalize any admin URL down to the part after /wp-admin/ so we can
// compare the current page against menu targets regardless of host/scheme.
const normalize = (url) => {
  if (!url) return '';
  const idx = url.indexOf('/wp-admin/');
  const path = idx === -1 ? url : url.slice(idx + '/wp-admin/'.length);
  // The bare admin root ("/wp-admin/" with nothing after it) IS the
  // dashboard, same as an explicit index.php — normalize them to one form
  // so the Dashboard section correctly reads as active/expanded there.
  return path === '' || path.startsWith('?') ? 'index.php' + path : path;
};

const current = computed(() => normalize(props.currentUrl));

const isItemActive = (item) => {
  if (!item.url) return false;
  return normalize(item.url) === current.value;
};

const isSectionActive = (item) => {
  if (isItemActive(item)) return true;
  if ((item.submenu || []).some((sub) => normalize(sub.url) === current.value)) return true;
  // Covers native "hidden child" screens WP core itself doesn't list as a
  // submenu link but still treats as belonging to this section for
  // highlighting purposes (theme-install.php under Appearance, media-new.php
  // under Media, user-new.php under Users, ...) — see Shell::print_mount()'s
  // activeParentFile, sourced from WP core's own $parent_file global.
  return !!props.activeParentFile && item.id === props.activeParentFile;
};

// Accordion: only one section's submenu open at a time — the WP-native
// menu list and the Aurora branded section below it are one shared
// accordion, not two independent ones, so expanding either closes
// whichever else was open (including Aurora, previously the one
// exception). AURORA_ID is a sentinel alongside the real native-menu item
// ids, since Aurora's own nav isn't one of the `menu` array's entries.
const AURORA_ID = '__aurora__';

const isAuroraSectionActive = () =>
  props.auroraNav.some((item) => normalize(item.url) === current.value);

// Starts on whichever section is active (each nav is a full page load in
// this multi-page admin, so there's no need to react to URL changes
// afterward); falls back to Aurora expanded (its long-standing default)
// unless the user previously collapsed it, matching the old per-browser
// localStorage preference.
const openId = ref(
  props.menu.find((item) => isSectionActive(item))?.id ??
    (isAuroraSectionActive() || localStorage.getItem('aurora-nav-expanded') !== '0'
      ? AURORA_ID
      : null)
);

const isExpanded = (item) => isSearching.value || openId.value === item.id;

const toggle = (item) => {
  openId.value = openId.value === item.id ? null : item.id;
};

const auroraExpanded = computed(() => isSearching.value || openId.value === AURORA_ID);
const toggleAuroraNav = () => {
  openId.value = auroraExpanded.value ? null : AURORA_ID;
  // Only Aurora's own expand state needs to persist across page loads —
  // native sections already resolve their open/closed state from whether
  // they're active on the current page.
  localStorage.setItem('aurora-nav-expanded', auroraExpanded.value ? '1' : '0');
};
const isAuroraActive = (item) => normalize(item.url) === current.value;

// Settings > Menu > "Show a search box at the top of the admin menu" —
// filters both the WP-native menu list and the Aurora nav section by
// title (own item or any submenu item), auto-expanding every section with
// a match so results are visible without also having to click into them.
const showMenuSearch = computed(() => !!props.serverData?.settings?.menu_search);
const searchQuery = ref('');
const isSearching = computed(() => searchQuery.value.trim() !== '');
const matchesQuery = (text) =>
  (text || '').toLowerCase().includes(searchQuery.value.trim().toLowerCase());

const filteredMenu = computed(() => {
  if (!isSearching.value) return props.menu;
  return props.menu
    .filter((item) => !item.separator)
    .map((item) => {
      const titleMatch = matchesQuery(item.title);
      const matchingSubs = (item.submenu || []).filter((sub) => matchesQuery(sub.title));
      if (!titleMatch && !matchingSubs.length) return null;
      return titleMatch ? item : { ...item, submenu: matchingSubs };
    })
    .filter(Boolean);
});

const filteredAuroraNav = computed(() => {
  if (!isSearching.value) return props.auroraNav;
  return props.auroraNav.filter((item) => matchesQuery(item.title));
});
</script>

<template>
  <nav class="sidebar" :class="{ 'sidebar--collapsed': collapsed }">
    <div class="sidebar__scroll">
    <div class="sidebar__brand">
      <img v-if="activeLogo" :src="activeLogo" alt="" class="sidebar__brand-img" />
      <template v-else>{{ collapsed ? 'A' : 'Aurora' }}</template>
    </div>

    <div v-if="showMenuSearch && !collapsed" class="sidebar__menu-search">
      <span class="dashicons dashicons-search" />
      <input v-model="searchQuery" type="text" placeholder="Search menu…" />
    </div>

    <ul class="sidebar__list">
      <template v-for="(item, index) in filteredMenu" :key="item.id || index">
        <li v-if="item.separator" class="sidebar__separator" />

        <li v-else class="sidebar__item">
          <div
            class="sidebar__row"
            :class="{ 'sidebar__row--active': isSectionActive(item) }"
          >
            <a :href="item.url" class="sidebar__link" :title="collapsed ? item.title : null">
              <span class="sidebar__icon">
                <span
                  v-if="item.icon && item.icon.type === 'dashicon'"
                  class="dashicons"
                  :class="item.icon.value"
                />
                <img
                  v-else-if="item.icon && item.icon.type === 'image'"
                  :src="item.icon.value"
                  alt=""
                  class="sidebar__icon-img"
                />
                <span v-else class="sidebar__icon-dot" />
              </span>
              <span class="sidebar__title">{{ item.title }}</span>
              <span v-if="item.count" class="sidebar__count">{{ item.count }}</span>
            </a>
            <button
              v-if="item.submenu && item.submenu.length && !isSearching"
              type="button"
              class="sidebar__chevron"
              :class="{ 'sidebar__chevron--open': isExpanded(item) }"
              :aria-label="`Toggle ${item.title} submenu`"
              @click="toggle(item)"
            >
              ›
            </button>
          </div>

          <ul
            v-if="item.submenu && item.submenu.length && isExpanded(item)"
            class="sidebar__sublist"
          >
            <li
              v-for="(sub, subIndex) in item.submenu"
              :key="sub.id || subIndex"
              class="sidebar__subitem"
            >
              <a
                :href="sub.url"
                class="sidebar__sublink"
                :class="{ 'sidebar__sublink--active': normalize(sub.url) === current }"
              >
                {{ sub.title }}
                <span v-if="sub.count" class="sidebar__count">{{ sub.count }}</span>
              </a>
            </li>
          </ul>
        </li>
      </template>
    </ul>

    <p v-if="isSearching && !filteredMenu.length && !filteredAuroraNav.length" class="sidebar__no-results">
      No matching menu items.
    </p>

    <div v-if="filteredAuroraNav.length" class="sidebar__separator" />

    <div v-if="filteredAuroraNav.length" class="sidebar__aurora">
      <div class="sidebar__row sidebar__aurora-brand" @click="toggleAuroraNav">
        <a href="#" class="sidebar__link" @click.prevent :title="collapsed ? 'Aurora' : null">
          <span class="sidebar__icon">
            <span class="dashicons dashicons-superhero-alt" />
          </span>
          <span class="sidebar__title">Aurora</span>
        </a>
        <button
          v-if="!isSearching"
          type="button"
          class="sidebar__chevron"
          :class="{ 'sidebar__chevron--open': auroraExpanded }"
          aria-label="Toggle Aurora menu"
        >
          ›
        </button>
      </div>

      <ul v-if="auroraExpanded" class="sidebar__sublist">
        <li v-for="item in filteredAuroraNav" :key="item.id" class="sidebar__subitem">
          <a
            :href="item.url"
            class="sidebar__sublink"
            :class="{ 'sidebar__sublink--active': isAuroraActive(item) }"
          >
            {{ item.title }}
          </a>
        </li>
      </ul>
    </div>
    </div>

    <UserMenu
      :server-data="serverData"
      :collapsed="collapsed"
    />
  </nav>
</template>

<style scoped>
.sidebar {
  position: fixed;
  top: var(--aurora-sidebar-gap);
  left: var(--aurora-sidebar-gap);
  bottom: var(--aurora-sidebar-gap);
  width: var(--aurora-sidebar-w);
  height: auto;
  /* Same surface treatment as the dashboard cards (AuroraCard.vue) —
     solid card background + rounded corners, floating over the page
     gradient with a gap on every side now that it's inset instead of
     flush against the viewport edges. */
  background: var(--aurora-card-bg);
  border: 1px solid var(--aurora-card-border);
  border-radius: var(--aurora-radius-lg);
  transition: width 0.2s ease;
  z-index: 99999;
  box-sizing: border-box;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  display: flex;
  flex-direction: column;
}

/* Scrollable nav content — separate from the pinned UserMenu footer below
   it, so the account card stays visible regardless of scroll position. */
.sidebar__scroll {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 12px 0;
  box-sizing: border-box;
}

.sidebar__brand {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--aurora-text);
  padding: 8px 20px 16px;
  letter-spacing: -0.02em;
}

.sidebar__brand-img {
  display: block;
  max-height: 30px;
  max-width: 190px;
  object-fit: contain;
}

.sidebar__menu-search {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 12px 10px;
  padding: 6px 10px;
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text-muted);
}
.sidebar__menu-search .dashicons {
  font-size: 15px;
  width: 15px;
  height: 15px;
  flex-shrink: 0;
}
.sidebar__menu-search input {
  flex: 1;
  min-width: 0;
  border: none;
  background: none;
  outline: none;
  color: var(--aurora-text);
  font-size: 0.8125rem;
}
.sidebar__menu-search input::placeholder {
  color: var(--aurora-text-muted);
}

.sidebar__no-results {
  margin: 4px 16px;
  font-size: 0.8125rem;
  color: var(--aurora-text-muted);
}

.sidebar__list,
.sidebar__sublist {
  list-style: none;
  margin: 0;
  padding: 0;
}

.sidebar__separator {
  height: 1px;
  background: var(--aurora-border);
  margin: 8px 16px;
}

.sidebar__row {
  display: flex;
  align-items: center;
  border-radius: var(--aurora-radius-sm);
  margin: 0 8px;
}

.sidebar__row--active {
  background: var(--aurora-accent-soft);
}

.sidebar__link {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  padding: 2px 10px;
  text-decoration: none;
  color: var(--aurora-text);
  font-size: 0.875rem;
  min-width: 0;
}

.sidebar__row--active .sidebar__link {
  color: var(--aurora-accent);
  font-weight: 600;
}

.sidebar__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  flex-shrink: 0;
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text-muted);
  transition: background 0.12s ease, color 0.12s ease;
}

.sidebar__row--active .sidebar__icon {
  background: var(--aurora-accent-soft);
  color: var(--aurora-accent);
}

.sidebar__icon .dashicons {
  font-size: 16px;
  width: 16px;
  height: 16px;
}

.sidebar__icon-img {
  width: 16px;
  height: 16px;
  object-fit: contain;
}

.sidebar__icon-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--aurora-text-muted);
}

.sidebar__title {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar__count {
  background: var(--aurora-accent);
  color: var(--aurora-accent-text);
  font-size: 0.6875rem;
  font-weight: 600;
  border-radius: 999px;
  padding: 1px 7px;
  flex-shrink: 0;
}

.sidebar__chevron {
  background: none;
  border: none;
  color: var(--aurora-text-muted);
  font-size: 1.1rem;
  cursor: pointer;
  padding: 4px 12px;
  transition: transform 0.15s ease;
}

.sidebar__chevron--open {
  transform: rotate(90deg);
}

.sidebar__sublist {
  padding: 2px 0 6px;
}

.sidebar__sublink {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 2px 10px 2px 42px;
  text-decoration: none;
  color: var(--aurora-text-muted);
  font-size: 0.8125rem;
  border-radius: var(--aurora-radius-sm);
  margin: 0 8px;
}

.sidebar__sublink:hover {
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
}

.sidebar__sublink--active {
  color: var(--aurora-accent);
  font-weight: 600;
}

/* --- Aurora branded nav (right under the native menu's own divider) ---- */
.sidebar__aurora {
  padding-bottom: 4px;
}
.sidebar__aurora-brand {
  cursor: pointer;
  font-weight: 600;
}
.sidebar__aurora-brand .sidebar__title {
  color: var(--aurora-text-muted);
  font-size: 0.8125rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.sidebar__aurora-brand .sidebar__icon {
  background: var(--aurora-accent-soft);
  color: var(--aurora-accent);
}

/* --- Collapsed (icon-only) rail --------------------------------------- */
.sidebar--collapsed {
  overflow-x: hidden;
}
.sidebar--collapsed .sidebar__brand {
  padding: 8px 0 16px;
  text-align: center;
  font-size: 1.1rem;
}
.sidebar--collapsed .sidebar__brand-img {
  margin: 0 auto;
  max-width: 40px;
}
.sidebar--collapsed .sidebar__title,
.sidebar--collapsed .sidebar__count,
.sidebar--collapsed .sidebar__chevron,
.sidebar--collapsed .sidebar__sublist {
  display: none;
}
.sidebar--collapsed .sidebar__row {
  margin: 1px 10px;
}
.sidebar--collapsed .sidebar__link {
  justify-content: center;
  padding: 10px 0;
  gap: 0;
}
</style>
