<script setup>
import { reactive, ref, onMounted } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const contentType = ref('all');

const filters = reactive({
  category: '',
  post_author: '',
  post_start_date: '',
  post_end_date: '',
  post_status: '',
  page_author: '',
  page_start_date: '',
  page_end_date: '',
  page_status: '',
  attachment_start_date: '',
  attachment_end_date: '',
});

// Standard WP post statuses (matches core's own get_post_stati({internal: false})).
const STATUSES = [
  { value: '', label: 'All' },
  { value: 'publish', label: 'Published' },
  { value: 'future', label: 'Scheduled' },
  { value: 'draft', label: 'Draft' },
  { value: 'pending', label: 'Pending Review' },
  { value: 'private', label: 'Private' },
  { value: 'trash', label: 'Trash' },
];

const categories = ref([]);
const authors = ref([]);
const loadingOptions = ref(true);

onMounted(async () => {
  try {
    const headers = { 'X-WP-Nonce': props.serverData.restNonce };
    const [catsRes, usersRes] = await Promise.all([
      fetch(`${props.serverData.restUrl}wp/v2/categories?per_page=100`, { headers }),
      fetch(`${props.serverData.restUrl}wp/v2/users?per_page=100`, { headers }),
    ]);
    categories.value = catsRes.ok ? await catsRes.json() : [];
    authors.value = usersRes.ok ? await usersRes.json() : [];
  } finally {
    loadingOptions.value = false;
  }
});

// Building and navigating to a real export.php?download=true&... URL (not a
// fetch) so the browser's normal file-download flow handles the response —
// this is the exact same request native WordPress's own form would submit,
// so the file it returns is unmodified.
//
// Every relevant param is always sent, even when blank. Native's own HTML
// <select> elements always submit a name=value pair regardless of which
// option is picked, and export.php's own PHP reads $_GET['cat'] etc.
// directly with no isset() guard — omitting a param when "All" is selected
// (as this used to) triggers PHP "Undefined array key" warnings that, with
// display_errors on (any site with debugging enabled), get echoed straight
// into the downloaded file before the XML, corrupting it.
const download = () => {
  const params = new URLSearchParams({ download: 'true', content: contentType.value });

  if (contentType.value === 'posts') {
    params.set('cat', filters.category);
    params.set('post_author', filters.post_author);
    params.set('post_start_date', filters.post_start_date);
    params.set('post_end_date', filters.post_end_date);
    params.set('post_status', filters.post_status);
  } else if (contentType.value === 'pages') {
    params.set('page_author', filters.page_author);
    params.set('page_start_date', filters.page_start_date);
    params.set('page_end_date', filters.page_end_date);
    params.set('page_status', filters.page_status);
  } else if (contentType.value === 'attachment') {
    params.set('attachment_start_date', filters.attachment_start_date);
    params.set('attachment_end_date', filters.attachment_end_date);
  }

  window.location.href = `${props.serverData.exportUrl}?${params.toString()}`;
};
</script>

<template>
  <main class="export-page">
    <header class="export-page__header">
      <p class="export-page__eyebrow">Aurora Admin</p>
      <h1>Export</h1>
      <p class="export-page__lead">
        Create an XML file (WordPress eXtended RSS, or WXR) containing your posts, pages, comments,
        custom fields, categories, and tags. Import it into another WordPress site to bring this
        content over.
      </p>
    </header>

    <section class="export-page__card">
      <h2>Choose what to export</h2>

      <div class="export-page__options">
        <label class="export-page__radio">
          <input type="radio" value="all" v-model="contentType" />
          <div>
            <strong>All content</strong>
            <p>All of your posts, pages, comments, custom fields, terms, navigation menus, and custom posts.</p>
          </div>
        </label>

        <label class="export-page__radio">
          <input type="radio" value="posts" v-model="contentType" />
          <strong>Posts</strong>
        </label>
        <div v-if="contentType === 'posts'" class="export-page__filters">
          <label>
            Category
            <select v-model="filters.category" :disabled="loadingOptions">
              <option value="">All</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </label>
          <label>
            Author
            <select v-model="filters.post_author" :disabled="loadingOptions">
              <option value="">All</option>
              <option v-for="a in authors" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
          </label>
          <label>Start date <input type="date" v-model="filters.post_start_date" /></label>
          <label>End date <input type="date" v-model="filters.post_end_date" /></label>
          <label>
            Status
            <select v-model="filters.post_status">
              <option v-for="s in STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </label>
        </div>

        <label class="export-page__radio">
          <input type="radio" value="pages" v-model="contentType" />
          <strong>Pages</strong>
        </label>
        <div v-if="contentType === 'pages'" class="export-page__filters">
          <label>
            Author
            <select v-model="filters.page_author" :disabled="loadingOptions">
              <option value="">All</option>
              <option v-for="a in authors" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
          </label>
          <label>Start date <input type="date" v-model="filters.page_start_date" /></label>
          <label>End date <input type="date" v-model="filters.page_end_date" /></label>
          <label>
            Status
            <select v-model="filters.page_status">
              <option v-for="s in STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </label>
        </div>

        <label class="export-page__radio">
          <input type="radio" value="attachment" v-model="contentType" />
          <strong>Media</strong>
        </label>
        <div v-if="contentType === 'attachment'" class="export-page__filters">
          <label>Start date <input type="date" v-model="filters.attachment_start_date" /></label>
          <label>End date <input type="date" v-model="filters.attachment_end_date" /></label>
        </div>
      </div>

      <button type="button" class="export-page__submit" @click="download">Download Export File</button>
    </section>
  </main>
</template>

<style scoped>
.export-page { min-height: calc(100vh - 112px); color: var(--aurora-text); background: transparent; }
.export-page__eyebrow {
  margin: 0 0 7px; color: var(--aurora-text-muted); font-size: 0.72rem;
  font-weight: 800; text-transform: uppercase;
}
.export-page__header h1 { margin: 0; font-size: 1.45rem; line-height: 1.15; }
.export-page__lead {
  margin: 12px 0 0; max-width: 640px; color: var(--aurora-text-muted);
  font-size: 0.875rem; line-height: 1.6;
}

.export-page__card {
  margin-top: 24px; background: var(--aurora-card-bg); border: 1px solid var(--aurora-card-border);
  border-radius: var(--aurora-radius-lg); padding: 22px 24px; max-width: 560px;
}
.export-page__card h2 { margin: 0 0 16px; font-size: 1rem; font-weight: 700; }

.export-page__options { display: flex; flex-direction: column; gap: 4px; }
.export-page__radio {
  display: flex; align-items: flex-start; gap: 10px; padding: 8px 4px;
  cursor: pointer; font-size: 0.875rem;
}
.export-page__radio input { margin-top: 3px; accent-color: var(--aurora-accent); }
.export-page__radio p { margin: 4px 0 0; font-size: 0.8125rem; color: var(--aurora-text-muted); font-weight: 400; }

.export-page__filters {
  display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
  margin: 0 0 8px 30px; padding: 12px; background: var(--aurora-bg-subtle);
  border-radius: var(--aurora-radius-md);
}
.export-page__filters label {
  display: flex; flex-direction: column; gap: 4px; font-size: 0.75rem;
  color: var(--aurora-text-muted); font-weight: 600;
}
.export-page__filters select, .export-page__filters input[type='date'] {
  padding: 6px 8px; border-radius: var(--aurora-radius-sm); border: 1px solid var(--aurora-border);
  background: var(--aurora-card-bg); color: var(--aurora-text); font-size: 0.8125rem; font-weight: 400;
}

.export-page__submit {
  margin-top: 20px; border: none; border-radius: var(--aurora-radius-sm); padding: 10px 20px;
  font-size: 0.875rem; font-weight: 600; cursor: pointer; background: var(--aurora-accent); color: #fff;
}
</style>
