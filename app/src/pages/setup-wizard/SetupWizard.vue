<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import SettingsField from '@/pages/settings/components/SettingsField.vue';
import { applyThemePalette, applyFontFamily } from '@/pages/settings/applyLiveTheme.js';

const props = defineProps({
  serverData: { type: Object, required: true },
});

// Step definitions. Welcome and Done are custom content; the middle three
// render their `fields` through the shared SettingsField component (same
// controls as the settings page — theme picker, font picker, media-library
// image fields, toggles).
const steps = [
  { id: 'welcome', label: 'Welcome' },
  {
    id: 'appearance',
    label: 'Appearance',
    title: 'Choose your look',
    subtitle:
      'Pick a color theme and font for the whole admin. You can change these any time in Settings.',
    fields: [
      {
        key: 'theme_preset',
        type: 'theme-picker',
        label: 'Theme',
        description: 'The accent and surface colors used across the admin.',
        default: 'indigo',
      },
      {
        key: 'font_family',
        type: 'font-picker',
        label: 'Font family',
        description:
          'Optional. Leave empty for your system font (no external requests). ' +
          'Choosing a font loads it from Google Fonts.',
      },
    ],
  },
  {
    id: 'branding',
    label: 'Branding',
    title: 'Add your branding',
    subtitle:
      'All optional — drop in a logo and favicon, or rename Aurora for a fully white-labeled admin.',
    fields: [
      { key: 'logo', type: 'image', label: 'Logo', description: 'Shown at the top of the sidebar menu.' },
      {
        key: 'dark_logo',
        type: 'image',
        label: 'Dark mode logo',
        description: 'Optional alternative logo; falls back to the standard one.',
      },
      {
        key: 'admin_favicon',
        type: 'image',
        label: 'Admin favicon',
        description: 'Replaces the WordPress favicon in the admin.',
      },
      {
        key: 'plugin_name',
        type: 'text',
        label: 'Rename Aurora Admin',
        description: 'White-labels the plugin name in menus and the plugins list.',
        placeholder: 'Aurora Admin',
      },
    ],
  },
  {
    id: 'features',
    label: 'Features',
    title: 'Choose your features',
    subtitle:
      'Aurora replaces these WordPress admin screens with faster versions of its own. Turn off any you’d rather keep native.',
    fields: [
      { key: 'use_custom_dashboard', type: 'toggle', label: 'Dashboard', description: 'Replace the WordPress dashboard with Aurora’s.', default: true },
      { key: 'modern_posts', type: 'toggle', label: 'Posts', description: 'Aurora posts management screen.', default: true },
      { key: 'modern_pages', type: 'toggle', label: 'Pages', description: 'Aurora pages management screen.', default: true },
      { key: 'modern_media', type: 'toggle', label: 'Media library', description: 'Aurora media library screen.', default: true },
      { key: 'modern_users', type: 'toggle', label: 'Users', description: 'Aurora users management screen.', default: true },
      { key: 'modern_comments', type: 'toggle', label: 'Comments', description: 'Aurora comments management screen.', default: true },
      { key: 'modern_plugins', type: 'toggle', label: 'Plugins', description: 'Aurora plugins management screen.', default: true },
      {
        key: 'full_admin_takeover',
        type: 'toggle',
        label: 'Full admin takeover',
        description: 'Also replace Appearance, Tools, and the native Settings screens. Off by default.',
        default: false,
      },
    ],
  },
  { id: 'done', label: 'Done', title: 'You’re all set' },
];

// Working settings, seeded from the server + each field's default.
const settings = reactive({ ...(props.serverData.settings || {}) });
steps.forEach((s) =>
  (s.fields || []).forEach((f) => {
    if (settings[f.key] === undefined && f.default !== undefined) settings[f.key] = f.default;
  })
);

const stepIndex = ref(0);
const current = computed(() => steps[stepIndex.value]);
const isFirst = computed(() => stepIndex.value === 0);
const isDone = computed(() => current.value.id === 'done');

const goNext = () => {
  if (stepIndex.value < steps.length - 1) stepIndex.value++;
};
const goBack = () => {
  if (stepIndex.value > 0) stepIndex.value--;
};

// Live preview of theme + font as they're picked (nothing is persisted until
// the wizard is finished, but the whole admin previews immediately).
watch(() => settings.theme_preset, (p) => applyThemePalette(p || 'indigo'), { immediate: true });
watch(() => settings.font_family, (f) => applyFontFamily(f), { immediate: true });

onMounted(() => {
  // The shell (which normally does this) is disabled on this page.
  document.documentElement.classList.add('aurora-dark');
  applyThemePalette(settings.theme_preset || 'indigo');
  applyFontFamily(settings.font_family);
});

const saving = ref(false);
const finish = async (target) => {
  if (saving.value) return;
  saving.value = true;
  try {
    await fetch(`${props.serverData.restUrl}wp/v2/settings`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': props.serverData.restNonce },
      body: JSON.stringify({ aurora_admin_settings: { ...settings } }),
    });
    await fetch(`${props.serverData.restUrl}aurora-admin/v1/setup/complete`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': props.serverData.restNonce },
    });
  } catch {
    /* Even if persistence fails, don't trap the user in the wizard. */
  }
  window.location.href = target || props.serverData.dashboardUrl || '/wp-admin/';
};
</script>

<template>
  <div class="wiz">
    <div class="wiz__inner">
      <div class="wiz__brand">
        <span class="wiz__mark">A</span>
        <span class="wiz__brandname">Aurora Admin</span>
      </div>

      <!-- Progress -->
      <ol class="wiz__steps">
        <template v-for="(s, i) in steps" :key="s.id">
          <li
            class="wiz__step"
            :class="{ 'is-active': i === stepIndex, 'is-done': i < stepIndex }"
          >
            <span class="wiz__dot">
              <span v-if="i < stepIndex" class="dashicons dashicons-yes" />
              <span v-else>{{ i + 1 }}</span>
            </span>
            <span class="wiz__step-label">{{ s.label }}</span>
          </li>
          <li v-if="i < steps.length - 1" class="wiz__connector" :class="{ 'is-on': i < stepIndex }" />
        </template>
      </ol>

      <!-- Card -->
      <section class="wiz__card">
        <!-- Welcome -->
        <div v-if="current.id === 'welcome'" class="wiz__welcome">
          <h1 class="wiz__h1">Welcome to Aurora Admin<span v-if="serverData.userName">, {{ serverData.userName }}</span></h1>
          <p class="wiz__lead">
            A modern, fast redesign of the WordPress admin. This quick setup takes about a
            minute — choose a look, add your branding, and pick which screens Aurora takes over.
            You can skip it and change everything later in Settings.
          </p>
        </div>

        <!-- Done -->
        <div v-else-if="current.id === 'done'" class="wiz__welcome">
          <span class="wiz__done-icon dashicons dashicons-yes-alt" />
          <h1 class="wiz__h1">You’re all set</h1>
          <p class="wiz__lead">
            Aurora is configured and ready. Head to your dashboard, or fine-tune anything in
            Settings.
          </p>
          <div class="wiz__done-actions">
            <button type="button" class="wiz__btn wiz__btn--primary" :disabled="saving" @click="finish(serverData.dashboardUrl)">
              {{ saving ? 'Finishing…' : 'Go to dashboard' }}
            </button>
            <button type="button" class="wiz__btn" :disabled="saving" @click="finish(serverData.settingsUrl)">
              Open settings
            </button>
          </div>
        </div>

        <!-- Field steps -->
        <div v-else>
          <h1 class="wiz__h1">{{ current.title }}</h1>
          <p v-if="current.subtitle" class="wiz__lead">{{ current.subtitle }}</p>
          <div class="wiz__fields">
            <SettingsField
              v-for="f in current.fields"
              :key="f.key"
              :field="f"
              v-model="settings[f.key]"
            />
          </div>
        </div>
      </section>

      <!-- Footer nav (hidden on the Done step, which has its own actions) -->
      <div v-if="!isDone" class="wiz__nav">
        <button type="button" class="wiz__btn wiz__btn--ghost" :disabled="saving" @click="finish(serverData.dashboardUrl)">
          Skip setup
        </button>
        <div class="wiz__nav-right">
          <button v-if="!isFirst" type="button" class="wiz__btn" @click="goBack">Back</button>
          <button type="button" class="wiz__btn wiz__btn--primary" @click="goNext">
            {{ isFirst ? 'Get started' : 'Continue' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wiz {
  position: fixed;
  inset: 0;
  z-index: 100000;
  overflow-y: auto;
  background:
    radial-gradient(120% 90% at 50% 0%, color-mix(in srgb, var(--aurora-accent) 12%, var(--aurora-app-bg-solid)) 0%, var(--aurora-app-bg-solid) 65%);
  color: var(--aurora-text);
  font-family: var(--aurora-font-family);
}
.wiz__inner {
  max-width: 760px;
  margin: 0 auto;
  padding: 48px 24px 64px;
}

.wiz__brand { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 30px; }
.wiz__mark {
  width: 30px; height: 30px; border-radius: var(--aurora-radius-md);
  background: var(--aurora-accent); color: #fff;
  display: flex; align-items: center; justify-content: center; font-weight: 700;
}
.wiz__brandname { font-size: 1rem; font-weight: 500; }

/* Progress */
.wiz__steps { display: flex; align-items: center; justify-content: center; list-style: none; margin: 0 0 30px; padding: 0; }
.wiz__step { display: flex; flex-direction: column; align-items: center; gap: 7px; flex: 0 0 auto; }
.wiz__dot {
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.75rem; font-weight: 500;
  border: 1.5px solid var(--aurora-border); background: var(--aurora-card-bg); color: var(--aurora-text-muted);
}
.wiz__dot .dashicons { font-size: 16px; width: 16px; height: 16px; }
.wiz__step.is-done .wiz__dot { background: var(--aurora-accent); border-color: var(--aurora-accent); color: #fff; }
.wiz__step.is-active .wiz__dot { border-color: var(--aurora-accent); color: var(--aurora-accent); }
.wiz__step-label { font-size: 0.6875rem; color: var(--aurora-text-muted); }
.wiz__step.is-active .wiz__step-label, .wiz__step.is-done .wiz__step-label { color: var(--aurora-text); }
.wiz__connector { flex: 1 1 auto; height: 1.5px; min-width: 22px; background: var(--aurora-border); margin: 0 6px; position: relative; top: -10px; }
.wiz__connector.is-on { background: var(--aurora-accent); }

/* Card */
.wiz__card {
  background: var(--aurora-card-bg);
  border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-xl);
  padding: 30px 32px;
}
.wiz__h1 { font-size: 1.5rem; font-weight: 600; margin: 0; }
.wiz__lead { margin: 10px 0 0; font-size: 0.9rem; color: var(--aurora-text-muted); line-height: 1.6; }

.wiz__welcome { text-align: center; padding: 20px 8px; }
.wiz__welcome .wiz__lead { max-width: 520px; margin: 12px auto 0; }
.wiz__done-icon { font-size: 52px; width: 52px; height: 52px; color: var(--aurora-accent); }
.wiz__done-actions { display: flex; gap: 12px; justify-content: center; margin-top: 26px; }

/* Fields: SettingsField renders its own rows; drop the first row's top pad
   and the last row's divider so they sit cleanly inside the card. */
.wiz__fields { margin-top: 8px; }
.wiz__fields > :deep(.field):first-child { padding-top: 12px; }
.wiz__fields > :deep(.field):last-child { border-bottom: none; padding-bottom: 4px; }

/* Nav */
.wiz__nav { display: flex; align-items: center; justify-content: space-between; margin-top: 22px; }
.wiz__nav-right { display: flex; gap: 10px; }
.wiz__btn {
  font-size: 0.875rem; font-weight: 500;
  padding: 10px 20px; border-radius: var(--aurora-radius-md); cursor: pointer;
  border: 1px solid var(--aurora-border); background: var(--aurora-card-bg); color: var(--aurora-text);
}
.wiz__btn:hover { border-color: var(--aurora-accent); }
.wiz__btn:disabled { opacity: 0.6; cursor: default; }
.wiz__btn--ghost { border-color: transparent; color: var(--aurora-text-muted); }
.wiz__btn--ghost:hover { color: var(--aurora-text); border-color: transparent; }
.wiz__btn--primary { background: var(--aurora-accent); color: #fff; border-color: var(--aurora-accent); }
.wiz__btn--primary:hover { filter: brightness(1.08); }
</style>
