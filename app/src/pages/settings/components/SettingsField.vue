<script setup>
import { computed, ref } from 'vue';
import Toggle from '@/components/Toggle.vue';
import { themePresets, buildPalette } from '@/pages/settings/themePresets.js';
import { googleFonts } from '@/pages/settings/googleFonts.js';

const swatchPalette = (preset) => buildPalette(preset);

const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { default: undefined },
  // Options for multiselect fields (resolved by the page from optionsSource).
  options: { type: Array, default: () => [] },
  // True when this field's `dependsOn` key is falsy — greys out the
  // control without hiding it, so the relationship stays visible.
  disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const val = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
});

// Repeater helpers ---------------------------------------------------------
const rows = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []));
const addRow = () => emit('update:modelValue', [...rows.value, { find: '', replace: '' }]);
const updateRow = (i, key, v) => {
  const next = rows.value.map((r, idx) => (idx === i ? { ...r, [key]: v } : r));
  emit('update:modelValue', next);
};
const removeRow = (i) => emit('update:modelValue', rows.value.filter((_, idx) => idx !== i));

// Multiselect helpers -------------------------------------------------------
const selectedValues = computed(() =>
  Array.isArray(props.modelValue) ? props.modelValue : []
);
const toggleValue = (value) => {
  const next = selectedValues.value.includes(value)
    ? selectedValues.value.filter((v) => v !== value)
    : [...selectedValues.value, value];
  emit('update:modelValue', next);
};

// Media picker (image fields) ---------------------------------------------
const pickImage = () => {
  const wp = window.wp;
  if (!wp || !wp.media) {
    // Fallback: prompt for a URL if the media library isn't available.
    const url = window.prompt('Image URL');
    if (url) emit('update:modelValue', url);
    return;
  }
  const frame = wp.media({ title: props.field.label, multiple: false });
  frame.on('select', () => {
    const att = frame.state().get('selection').first().toJSON();
    emit('update:modelValue', att.url);
  });
  frame.open();
};

// Font picker (searchable Google Font dropdown) ----------------------------
const fontQuery = ref('');
const fontOpen = ref(false);
const filteredFonts = computed(() => {
  const q = fontQuery.value.trim().toLowerCase();
  const list = q ? googleFonts.filter((f) => f.toLowerCase().includes(q)) : googleFonts;
  return list.slice(0, 40);
});
const pickFont = (name) => {
  emit('update:modelValue', name);
  fontQuery.value = name;
  fontOpen.value = false;
};
const openFontList = () => {
  // Start with an empty query so the whole font list is shown on focus.
  // Seeding it with the current font filtered the list down to just that
  // one font, making it impossible to pick a different one without first
  // clearing the box. The active font stays highlighted via `val === name`.
  fontQuery.value = '';
  fontOpen.value = true;
};

</script>

<template>
  <div class="field">
    <div class="field__info">
      <div class="field__label">{{ field.label }}</div>
      <p v-if="field.description" class="field__desc">{{ field.description }}</p>
    </div>

    <div class="field__control">
      <!-- Text -->
      <input
        v-if="field.type === 'text'"
        type="text"
        class="field__input"
        :placeholder="field.placeholder || ''"
        :readonly="!!field.readonly"
        :value="val || ''"
        @input="val = $event.target.value"
      />

      <!-- Textarea (multi-line plain text — moderation/blocklist fields) -->
      <textarea
        v-else-if="field.type === 'textarea'"
        class="field__input field__textarea"
        :class="{ 'field__textarea--mono': field.mono }"
        :placeholder="field.placeholder || ''"
        :rows="field.rows || 6"
        :value="val || ''"
        @input="val = $event.target.value"
      ></textarea>

      <!-- Select (plain dropdown, distinct from the multiselect checkbox list below) -->
      <select
        v-else-if="field.type === 'select'"
        class="field__input"
        :value="val ?? ''"
        :disabled="disabled"
        @change="val = $event.target.value"
      >
        <option v-for="opt in (field.options || options)" :key="opt.value" :value="opt.value">
          {{ opt.label }}
        </option>
      </select>

      <!-- Toggle -->
      <Toggle
        v-else-if="field.type === 'toggle'"
        :model-value="!!val"
        :disabled="disabled"
        @update:model-value="val = $event"
      />

      <!-- Color -->
      <div v-else-if="field.type === 'color'" class="field__color">
        <input
          type="color"
          :value="val || field.default || '#4772fa'"
          @input="val = $event.target.value"
        />
        <span class="field__color-hex">{{ val || field.default || '#4772fa' }}</span>
      </div>

      <!-- Image / favicon -->
      <button
        v-else-if="field.type === 'image'"
        type="button"
        class="field__image"
        @click="pickImage"
      >
        <img v-if="val" :src="val" alt="" />
        <span v-else class="dashicons dashicons-format-image field__image-ph" />
        <span class="field__image-label">{{ val ? 'Change' : 'Choose from media library' }}</span>
      </button>

      <!-- Multiselect (checkbox list) -->
      <div v-else-if="field.type === 'multiselect'" class="field__multi">
        <label v-for="opt in options" :key="opt.value" class="field__multi-item">
          <input
            type="checkbox"
            :checked="selectedValues.includes(opt.value)"
            @change="toggleValue(opt.value)"
          />
          <span>{{ opt.label }}</span>
        </label>
        <p v-if="!options.length" class="field__multi-empty">No options available.</p>
      </div>

      <!-- Segmented No/Yes -->
      <div v-else-if="field.type === 'segmented'" class="field__seg">
        <button
          type="button"
          :class="{ 'field__seg--active': !val }"
          @click="val = false"
        >
          No
        </button>
        <button
          type="button"
          :class="{ 'field__seg--active': !!val }"
          @click="val = true"
        >
          Yes
        </button>
      </div>


      <!-- Theme picker (10 fixed color presets, whole-UI preview) -->
      <div v-else-if="field.type === 'theme-picker'" class="field__theme">
        <button
          v-for="preset in themePresets"
          :key="preset.id"
          type="button"
          class="field__theme-card"
          :class="{ 'field__theme-card--active': (val || field.default) === preset.id }"
          @click="val = preset.id"
        >
          <span
            class="field__theme-swatch"
            :style="{
              background: swatchPalette(preset)['--aurora-app-bg'],
              borderColor: swatchPalette(preset)['--aurora-border'],
            }"
          >
            <span
              class="field__theme-swatch-accent"
              :style="{ background: swatchPalette(preset)['--aurora-accent'] }"
            />
          </span>
          <span class="field__theme-name">{{ preset.label }}</span>
        </button>
      </div>

      <!-- Font picker (searchable Google Font dropdown) -->
      <div v-else-if="field.type === 'font-picker'" class="field__font">
        <input
          type="text"
          class="field__input"
          placeholder="Search Google Fonts…"
          :value="fontOpen ? fontQuery : (val || 'Default')"
          @focus="openFontList"
          @input="fontQuery = $event.target.value"
          @blur="() => setTimeout(() => (fontOpen = false), 150)"
        />
        <div v-if="fontOpen" class="field__font-list">
          <button
            v-for="name in filteredFonts"
            :key="name"
            type="button"
            class="field__font-item"
            :class="{ 'field__font-item--active': val === name }"
            :style="{ fontFamily: `'${name}', sans-serif` }"
            @mousedown.prevent="pickFont(name)"
          >
            {{ name }}
          </button>
          <p v-if="!filteredFonts.length" class="field__multi-empty">No fonts match.</p>
        </div>
      </div>

      <!-- Repeater -->
      <div v-else-if="field.type === 'repeater'" class="field__repeater">
        <div v-for="(row, i) in rows" :key="i" class="field__repeater-row">
          <input
            type="text"
            class="field__input"
            :placeholder="field.columns ? field.columns[0] : 'Find'"
            :value="row.find"
            @input="updateRow(i, 'find', $event.target.value)"
          />
          <input
            type="text"
            class="field__input"
            :placeholder="field.columns ? field.columns[1] : 'Replace'"
            :value="row.replace"
            @input="updateRow(i, 'replace', $event.target.value)"
          />
          <button type="button" class="field__repeater-remove" @click="removeRow(i)">×</button>
        </div>
        <button type="button" class="field__newitem" @click="addRow">New item</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.field {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 32px;
  padding: 28px 0;
  border-bottom: 1px solid var(--aurora-border);
  align-items: start;
}
.field__label { font-size: 0.95rem; font-weight: 600; color: var(--aurora-text); }
.field__desc { margin: 6px 0 0; font-size: 0.8125rem; color: var(--aurora-text-muted); line-height: 1.5; }
.field__control { display: flex; justify-content: flex-start; }

.field__input {
  width: 100%;
  background: var(--aurora-bg-subtle);
  border: 1px solid var(--aurora-border);
  color: var(--aurora-text);
  border-radius: var(--aurora-radius-sm);
  padding: 9px 12px;
  font-size: 0.875rem;
}
.field__input:focus { outline: none; border-color: var(--aurora-accent); }
.field__textarea { resize: vertical; font-family: inherit; line-height: 1.5; }
.field__textarea--mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, 'Liberation Mono', monospace;
  font-size: 0.8125rem;
  tab-size: 2;
}

.field__color { display: flex; align-items: center; gap: 10px; }
.field__color input[type='color'] {
  width: 40px; height: 32px; border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-sm); background: none; cursor: pointer; padding: 2px;
}
.field__color-hex { font-size: 0.8125rem; color: var(--aurora-text-muted); }

.field__image {
  width: 160px; height: 120px;
  border: 1px dashed var(--aurora-border);
  border-radius: var(--aurora-radius-md);
  background: var(--aurora-bg-subtle);
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 8px; cursor: pointer; color: var(--aurora-text-muted);
  position: relative; overflow: hidden;
}
.field__image img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; }
.field__image-ph { font-size: 28px; width: 28px; height: 28px; }
.field__image-label {
  position: absolute; bottom: 0; left: 0; right: 0;
  background: var(--aurora-card-bg); padding: 6px; font-size: 0.75rem;
  border-top: 1px solid var(--aurora-border);
}

.field__repeater { width: 100%; }
.field__repeater-row { display: flex; gap: 8px; margin-bottom: 8px; }
.field__repeater-remove {
  flex-shrink: 0; width: 34px; border: 1px solid var(--aurora-border);
  background: var(--aurora-bg-subtle); color: var(--aurora-text-muted);
  border-radius: var(--aurora-radius-sm); cursor: pointer; font-size: 1.1rem;
}
.field__newitem {
  border: 1px solid var(--aurora-border);
  background: var(--aurora-bg-subtle);
  color: var(--aurora-text);
  border-radius: var(--aurora-radius-sm); padding: 8px 16px; font-size: 0.8125rem; cursor: pointer;
}
.field__newitem:hover { border-color: var(--aurora-accent); color: var(--aurora-accent); }

/* Multiselect */
.field__multi { display: flex; flex-direction: column; gap: 8px; width: 100%; }
.field__multi-item {
  display: flex; align-items: center; gap: 8px;
  font-size: 0.8125rem; color: var(--aurora-text); cursor: pointer;
}
.field__multi-item input { accent-color: var(--aurora-accent); }
.field__multi-empty { font-size: 0.8125rem; color: var(--aurora-text-muted); margin: 0; }

/* Segmented No/Yes */
.field__seg {
  display: inline-flex;
  background: var(--aurora-bg-subtle);
  border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-md);
  padding: 3px;
  gap: 2px;
}
.field__seg button {
  border: none; background: none; cursor: pointer;
  padding: 6px 26px; border-radius: var(--aurora-radius-sm);
  font-size: 0.8125rem; color: var(--aurora-text-muted);
}
.field__seg--active { background: var(--aurora-accent); color: #fff !important; }

/* Theme picker */
.field__theme { display: flex; flex-wrap: wrap; gap: 10px; width: 100%; }
.field__theme-card {
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  width: 76px; padding: 10px 6px;
  border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-md);
  background: var(--aurora-bg-subtle);
  cursor: pointer;
}
.field__theme-card--active { border-color: var(--aurora-accent); box-shadow: 0 0 0 1px var(--aurora-accent); }
.field__theme-swatch {
  width: 36px; height: 36px; border-radius: var(--aurora-radius-md);
  border: 1px solid var(--aurora-card-border);
  display: flex; align-items: flex-end; justify-content: flex-end;
  padding: 4px;
  box-sizing: border-box;
}
.field__theme-swatch-accent {
  width: 10px; height: 10px; border-radius: 50%;
}
.field__theme-name { font-size: 0.6875rem; color: var(--aurora-text-muted); }

/* Font picker */
.field__font { position: relative; width: 100%; max-width: 320px; }
.field__font-list {
  position: absolute; top: calc(100% + 4px); left: 0; right: 0;
  max-height: 260px; overflow-y: auto; z-index: 20;
  background: var(--aurora-card-bg);
  border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-md);
  padding: 6px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}
.field__font-item {
  display: block; width: 100%; text-align: left;
  border: none; background: none; cursor: pointer;
  padding: 8px 10px; border-radius: var(--aurora-radius-sm);
  font-size: 0.875rem; color: var(--aurora-text);
}
.field__font-item:hover { background: var(--aurora-bg-subtle); }
.field__font-item--active { color: var(--aurora-accent); }

@media (max-width: 782px) {
  .field { grid-template-columns: 1fr; gap: 12px; }
}
</style>
