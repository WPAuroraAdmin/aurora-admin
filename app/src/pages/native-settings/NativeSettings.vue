<script setup>
import { computed, reactive, ref } from 'vue';
import SettingsField from '@/pages/settings/components/SettingsField.vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

// Permalinks (rewrite rules) and Discussion (core's REST settings schema
// only exposes 2 of its ~15 real options — see DiscussionData.php) both
// have their own aurora-admin/v1 routes instead of going through
// wp/v2/settings like every other native-settings screen.
const isPermalinks = props.serverData.screen === 'permalinks';
const isDiscussion = props.serverData.screen === 'discussion';

const values = reactive({ ...(props.serverData.values || {}) });

// Mirrors options-permalink.php's own preset list — the actual reason
// people visit this screen; the "Custom Structure" text field alone
// (this screen's only original field) skipped the part of the native
// page virtually everyone actually uses instead of hand-writing tags.
const permalinkPresets = [
  { key: 'plain', label: 'Plain', structure: '', example: '?p=123' },
  {
    key: 'day',
    label: 'Day and name',
    structure: '/%year%/%monthnum%/%day%/%postname%/',
    example: '/2026/01/28/sample-post/',
  },
  {
    key: 'month',
    label: 'Month and name',
    structure: '/%year%/%monthnum%/%postname%/',
    example: '/2026/01/sample-post/',
  },
  {
    key: 'numeric',
    label: 'Numeric',
    structure: '/archives/%post_id%',
    example: '/archives/123',
  },
  {
    key: 'postname',
    label: 'Post name',
    structure: '/%postname%/',
    example: '/sample-post/',
  },
  { key: 'custom', label: 'Custom Structure', structure: null, example: '' },
];

// Fields can declare showIf: { key, equals } to only appear once another
// field's current value matches — e.g. the front-page/posts-page pickers
// only make sense once "Homepage Displays" is set to "A static page",
// matching the native Reading screen's own conditional reveal.
const visibleFields = computed(() =>
  props.serverData.fields.filter((f) => !f.showIf || values[f.showIf.key] === f.showIf.equals)
);

const selectedPreset = computed(() => {
  const match = permalinkPresets.find(
    (p) => p.structure !== null && p.structure === (values.permalink_structure || '')
  );
  return match ? match.key : 'custom';
});

const choosePreset = (preset) => {
  if (preset.key === 'custom') {
    // Leave whatever custom value is already there (or empty) — this
    // option just reveals the text field instead of assigning a value.
    if (selectedPreset.value !== 'custom') values.permalink_structure = '';
    return;
  }
  values.permalink_structure = preset.structure;
};

const saving = ref(false);
const savedMsg = ref('');

const save = async () => {
  saving.value = true;
  savedMsg.value = '';
  try {
    const url = isPermalinks
      ? `${props.serverData.restUrl}aurora-admin/v1/permalinks`
      : isDiscussion
        ? `${props.serverData.restUrl}aurora-admin/v1/discussion-settings`
        : `${props.serverData.restUrl}wp/v2/settings`;

    const body = isPermalinks
      ? { structure: values.permalink_structure || '' }
      : isDiscussion
        ? {
            values: Object.fromEntries(
              props.serverData.fields.filter((f) => !f.readonly).map((f) => [f.key, values[f.key]])
            ),
          }
        : Object.fromEntries(
            props.serverData.fields
              .filter((f) => !f.readonly)
              .map((f) => [f.key, values[f.key]])
          );

    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': props.serverData.restNonce },
      body: JSON.stringify(body),
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
</script>

<template>
  <div class="native-settings">
    <header class="native-settings__head">
      <div>
        <h2 class="native-settings__title">{{ serverData.title }}</h2>
        <p class="native-settings__sub">{{ serverData.subtitle }}</p>
      </div>
      <div class="native-settings__save-wrap">
        <span v-if="savedMsg" class="native-settings__saved">{{ savedMsg }}</span>
        <button type="button" class="native-settings__save" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </div>
    </header>

    <div v-if="isPermalinks" class="native-settings__presets">
      <label v-for="preset in permalinkPresets" :key="preset.key" class="native-settings__preset">
        <input
          type="radio"
          name="permalink-preset"
          :checked="selectedPreset === preset.key"
          @change="choosePreset(preset)"
        />
        <span class="native-settings__preset-label">{{ preset.label }}</span>
        <code v-if="preset.example" class="native-settings__preset-example">{{ preset.example }}</code>
      </label>
    </div>

    <div class="native-settings__fields">
      <SettingsField
        v-for="f in visibleFields"
        :key="f.key"
        :field="f"
        v-model="values[f.key]"
      />
    </div>
  </div>
</template>

<style scoped>
.native-settings {
  max-width: 720px;
  margin: 0 auto;
}
.native-settings__head {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 24px;
  padding-bottom: 22px;
  margin-bottom: 4px;
  border-bottom: 1px solid var(--aurora-frame-border);
}
.native-settings__title { font-size: 1.6rem; font-weight: 700; margin: 0; color: var(--aurora-text); }
.native-settings__sub { margin: 6px 0 0; font-size: 0.875rem; color: var(--aurora-text-muted); }
.native-settings__save-wrap { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.native-settings__saved { font-size: 0.8125rem; color: var(--aurora-text-muted); }
.native-settings__save {
  background: var(--aurora-accent); color: #fff; border: none;
  border-radius: var(--aurora-radius-md); padding: 9px 20px; font-size: 0.875rem; font-weight: 500; cursor: pointer;
}
.native-settings__save:disabled { opacity: 0.6; cursor: default; }
.native-settings__fields > :last-child { border-bottom: none; }

.native-settings__presets {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 18px 0;
  border-bottom: 1px solid var(--aurora-frame-border);
}
.native-settings__preset {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 0;
  cursor: pointer;
  font-size: 0.875rem;
  color: var(--aurora-text);
}
.native-settings__preset input[type='radio'] {
  accent-color: var(--aurora-accent);
  width: 15px;
  height: 15px;
  flex-shrink: 0;
}
.native-settings__preset-label {
  min-width: 150px;
  flex-shrink: 0;
  font-weight: 500;
}
.native-settings__preset-example {
  color: var(--aurora-text-muted);
  font-size: 0.8125rem;
  background: var(--aurora-bg-subtle);
  padding: 2px 8px;
  border-radius: var(--aurora-radius-sm);
}
</style>
