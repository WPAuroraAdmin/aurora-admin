<script setup>
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
  open: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const input = ref(null);
const query = ref('');
const results = ref([]);
const loading = ref(false);
const highlighted = ref(0);

let debounceTimer;
let requestSeq = 0;

// wp/v2/search returns titles with HTML entities (e.g. "Ben &amp; Jerry").
// Decode them for display WITHOUT rendering markup: a <textarea>'s innerHTML
// is RCDATA, so tags stay literal text and only entities are decoded — then
// we read them back as a plain string and render with text interpolation.
// This replaces a v-html bind on item.title, which would have executed any
// HTML an unfiltered_html user put in a post title (admin→admin XSS).
const decodeEntities = (str) => {
  const el = document.createElement('textarea');
  el.innerHTML = String(str ?? '');
  return el.value;
};

const search = async () => {
  const q = query.value.trim();
  if (q.length < 2) {
    results.value = [];
    return;
  }

  const seq = ++requestSeq;
  loading.value = true;
  try {
    const url = new URL(`${props.serverData.restUrl}wp/v2/search`);
    url.searchParams.set('search', q);
    url.searchParams.set('per_page', '10');
    // Settings > General > "Search post types" limits which post types the
    // global search covers; empty means all searchable types.
    const types = props.serverData.settings?.search_post_types;
    if (Array.isArray(types) && types.length) {
      url.searchParams.set('subtype', types.join(','));
    }
    const res = await fetch(url, {
      headers: { 'X-WP-Nonce': props.serverData.restNonce },
    });
    if (!res.ok) throw new Error();
    const items = await res.json();
    if (seq === requestSeq) {
      results.value = items;
      highlighted.value = 0;
    }
  } catch {
    if (seq === requestSeq) results.value = [];
  } finally {
    if (seq === requestSeq) loading.value = false;
  }
};

watch(query, () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(search, 250);
});

watch(
  () => props.open,
  (open) => {
    if (open) {
      query.value = '';
      results.value = [];
      nextTick(() => input.value?.focus());
    }
  }
);

const openResult = (item) => {
  if (!item) return;
  window.location.href = `${props.serverData.adminUrl}post.php?post=${item.id}&action=edit`;
};

const onKeydown = (e) => {
  if (!props.open) return;
  if (e.key === 'Escape') {
    emit('close');
  } else if (e.key === 'ArrowDown') {
    e.preventDefault();
    highlighted.value = Math.min(highlighted.value + 1, results.value.length - 1);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    highlighted.value = Math.max(highlighted.value - 1, 0);
  } else if (e.key === 'Enter') {
    openResult(results.value[highlighted.value]);
  }
};

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="smodal__overlay" @click.self="emit('close')">
      <div class="smodal">
        <div class="smodal__inputwrap">
          <span class="dashicons dashicons-search smodal__icon" />
          <input
            ref="input"
            v-model="query"
            type="text"
            class="smodal__input"
            placeholder="Search posts, pages…"
          />
          <span class="smodal__esc">Esc</span>
        </div>

        <div v-if="loading" class="smodal__state">Searching…</div>
        <div v-else-if="query.trim().length >= 2 && !results.length" class="smodal__state">
          No results for “{{ query.trim() }}”
        </div>

        <ul v-if="results.length" class="smodal__results">
          <li v-for="(item, i) in results" :key="item.id">
            <button
              type="button"
              class="smodal__result"
              :class="{ 'smodal__result--active': i === highlighted }"
              @mouseenter="highlighted = i"
              @click="openResult(item)"
            >
              <span class="smodal__result-title">{{ decodeEntities(item.title) }}</span>
              <span class="smodal__result-type">{{ item.subtype }}</span>
            </button>
          </li>
        </ul>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.smodal__overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  z-index: 100000;
  display: flex;
  justify-content: center;
  padding-top: 12vh;
}
.smodal {
  width: min(620px, calc(100vw - 40px));
  align-self: flex-start;
  background: var(--aurora-frame-bg, #1c1c20);
  border: 1px solid var(--aurora-frame-border, #34343b);
  border-radius: var(--aurora-radius-md);
  box-shadow: 0 24px 64px rgba(0, 0, 0, 0.45);
  overflow: hidden;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.smodal__inputwrap {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--aurora-border, #27272a);
}
.smodal__icon { color: var(--aurora-text-muted); }
.smodal__input {
  flex: 1;
  border: none;
  background: none;
  outline: none;
  color: var(--aurora-text);
  font-size: 0.95rem;
}
.smodal__esc {
  font-size: 0.6875rem;
  color: var(--aurora-text-muted);
  border: 1px solid var(--aurora-border, #27272a);
  border-radius: var(--aurora-radius-sm);
  padding: 2px 6px;
}
.smodal__state {
  padding: 18px 16px;
  font-size: 0.875rem;
  color: var(--aurora-text-muted);
}
.smodal__results {
  list-style: none;
  margin: 0;
  padding: 6px;
  max-height: 50vh;
  overflow-y: auto;
}
.smodal__result {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  border: none;
  background: none;
  cursor: pointer;
  text-align: left;
  padding: 10px 12px;
  border-radius: var(--aurora-radius-md);
}
.smodal__result--active { background: var(--aurora-accent-soft); }
.smodal__result-title {
  color: var(--aurora-text);
  font-size: 0.875rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.smodal__result-type {
  flex-shrink: 0;
  font-size: 0.6875rem;
  color: var(--aurora-text-muted);
  border: 1px solid var(--aurora-border, #27272a);
  border-radius: 999px;
  padding: 2px 8px;
  text-transform: capitalize;
}
</style>
