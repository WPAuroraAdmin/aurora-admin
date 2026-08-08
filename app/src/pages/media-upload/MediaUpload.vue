<script setup>
import { ref } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const files = ref([]);
const dragging = ref(false);
const fileInput = ref(null);

const addFiles = (fileList) => {
  files.value.push(
    ...Array.from(fileList).map((file) => ({
      file,
      name: file.name,
      status: 'pending', // pending | uploading | done | error
      message: '',
      url: '',
    }))
  );
};

const onDrop = (e) => {
  dragging.value = false;
  addFiles(e.dataTransfer.files);
};

const onPick = (e) => {
  addFiles(e.target.files);
  e.target.value = '';
};

const uploadOne = async (entry) => {
  entry.status = 'uploading';
  try {
    const res = await fetch(`${props.serverData.restUrl}wp/v2/media`, {
      method: 'POST',
      headers: {
        'X-WP-Nonce': props.serverData.restNonce,
        'Content-Disposition': `attachment; filename="${entry.file.name}"`,
        'Content-Type': entry.file.type || 'application/octet-stream',
      },
      body: entry.file,
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data?.message || 'Upload failed.');
    entry.status = 'done';
    entry.url = data.source_url || '';
  } catch (e) {
    entry.status = 'error';
    entry.message = e.message || 'Upload failed.';
  }
};

const uploadAll = () => {
  files.value.filter((f) => f.status === 'pending').forEach(uploadOne);
};

const clearDone = () => {
  files.value = files.value.filter((f) => f.status !== 'done');
};
</script>

<template>
  <main class="media-upload-page">
    <header class="media-upload-page__header">
      <div>
        <p class="media-upload-page__eyebrow">Media</p>
        <h1>Add Media File</h1>
      </div>
      <a class="button" :href="serverData.libraryUrl">Back to Media Library</a>
    </header>

    <div
      class="media-upload-drop"
      :class="{ 'media-upload-drop--active': dragging }"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
      @click="fileInput.click()"
    >
      <span class="dashicons dashicons-upload" />
      <p>Drop files here, or click to select files</p>
      <input ref="fileInput" type="file" multiple hidden @change="onPick" />
    </div>

    <section v-if="files.length" class="media-upload-list">
      <div class="media-upload-list__actions">
        <button type="button" class="button-primary" @click="uploadAll">Upload All</button>
        <button type="button" class="button" @click="clearDone">Clear Completed</button>
      </div>

      <div v-for="entry in files" :key="entry.name + entry.file.lastModified" class="media-upload-item">
        <span class="media-upload-item__name">{{ entry.name }}</span>
        <span class="media-upload-item__status" :class="`media-upload-item__status--${entry.status}`">
          <template v-if="entry.status === 'pending'">Ready</template>
          <template v-else-if="entry.status === 'uploading'">Uploading…</template>
          <template v-else-if="entry.status === 'done'">
            Uploaded — <a :href="entry.url" target="_blank" rel="noopener">View</a>
          </template>
          <template v-else>{{ entry.message }}</template>
        </span>
      </div>
    </section>
  </main>
</template>

<style scoped>
.media-upload-page { max-width: 720px; margin: 0 auto; }
.media-upload-page__header {
  display: flex; align-items: flex-start; justify-content: space-between;
  margin-bottom: 20px;
}
.media-upload-page__eyebrow {
  margin: 0 0 4px; font-size: 0.75rem; text-transform: uppercase;
  letter-spacing: 0.06em; color: var(--aurora-text-muted);
}
.media-upload-page h1 { margin: 0; font-size: 1.6rem; font-weight: 700; color: var(--aurora-text); }

.media-upload-drop {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 10px; padding: 48px 24px; text-align: center; cursor: pointer;
  border: 2px dashed var(--aurora-border);
  border-radius: var(--aurora-radius-md);
  color: var(--aurora-text-muted);
  transition: border-color 0.15s, color 0.15s;
}
.media-upload-drop .dashicons { font-size: 32px; width: 32px; height: 32px; }
.media-upload-drop--active,
.media-upload-drop:hover { border-color: var(--aurora-accent); color: var(--aurora-text); }

.media-upload-list { margin-top: 20px; }
.media-upload-list__actions { display: flex; gap: 8px; margin-bottom: 12px; }

.media-upload-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 14px; border-radius: var(--aurora-radius-sm);
  background: var(--aurora-bg-subtle);
  border: 1px solid var(--aurora-border);
  margin-bottom: 6px; font-size: 0.875rem;
}
.media-upload-item__name { color: var(--aurora-text); }
.media-upload-item__status { color: var(--aurora-text-muted); }
.media-upload-item__status--done { color: #22c55e; }
.media-upload-item__status--error { color: #ef4444; }
.media-upload-item__status a { color: inherit; text-decoration: underline; }
</style>
