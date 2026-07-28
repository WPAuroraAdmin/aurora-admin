<script setup>
import { reactive, ref } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const description = ref('');
const sending = ref(false);
const notice = ref('');
const error = ref('');

const diagnostics = reactive({
  pluginVersion: props.serverData.pluginVersion || '',
  wpVersion: props.serverData.wpVersion || '',
  phpVersion: props.serverData.phpVersion || '',
  theme: props.serverData.theme || '',
});

const send = async () => {
  if (!description.value.trim()) return;

  sending.value = true;
  error.value = '';
  notice.value = '';
  try {
    const res = await fetch(`${props.serverData.restUrl}aurora-admin/v1/bug-report`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': props.serverData.restNonce,
      },
      body: JSON.stringify({ description: description.value }),
    });
    const data = await res.json();
    if (!res.ok || !data.success) throw new Error(data?.message || 'Could not send your report.');
    notice.value = 'Thanks — your report was sent.';
    description.value = '';
  } catch (e) {
    error.value = e.message;
  } finally {
    sending.value = false;
  }
};
</script>

<template>
  <main class="bug-report-page">
    <header class="bug-report-page__header">
      <p class="bug-report-page__eyebrow">Support</p>
      <h1>Report a Bug</h1>
      <p class="bug-report-page__sub">
        Tell us what went wrong. We'll get your diagnostics automatically —
        just describe the issue below.
      </p>
    </header>

    <div v-if="notice" class="bug-report-notice bug-report-notice--success">{{ notice }}</div>
    <div v-if="error" class="bug-report-notice bug-report-notice--error">{{ error }}</div>

    <form class="bug-report-form" @submit.prevent="send">
      <label class="bug-report-field">
        <span>What happened?</span>
        <textarea
          v-model="description"
          rows="8"
          placeholder="Describe the bug, what you expected, and the steps to reproduce it…"
          required
        />
      </label>

      <div class="bug-report-diagnostics">
        <h2>Diagnostics (sent automatically)</h2>
        <dl>
          <div><dt>Plugin version</dt><dd>{{ diagnostics.pluginVersion || '—' }}</dd></div>
          <div><dt>WordPress version</dt><dd>{{ diagnostics.wpVersion || '—' }}</dd></div>
          <div><dt>PHP version</dt><dd>{{ diagnostics.phpVersion || '—' }}</dd></div>
          <div><dt>Active theme</dt><dd>{{ diagnostics.theme || '—' }}</dd></div>
        </dl>
      </div>

      <div class="bug-report-form__actions">
        <button type="submit" class="button-primary" :disabled="sending || !description.trim()">
          {{ sending ? 'Sending…' : 'Send Report' }}
        </button>
      </div>
    </form>
  </main>
</template>

<style scoped>
.bug-report-page { max-width: 640px; margin: 0 auto; }
.bug-report-page__header { margin-bottom: 16px; }
.bug-report-page__eyebrow {
  margin: 0 0 4px; font-size: 0.75rem; text-transform: uppercase;
  letter-spacing: 0.06em; color: var(--aurora-text-muted);
}
.bug-report-page h1 { margin: 0; font-size: 1.6rem; font-weight: 700; color: var(--aurora-text); }
.bug-report-page__sub { margin: 8px 0 0; font-size: 0.875rem; color: var(--aurora-text-muted); }

.bug-report-notice { padding: 10px 14px; border-radius: var(--aurora-radius-sm); margin-bottom: 16px; font-size: 0.875rem; }
.bug-report-notice--success { background: rgba(34, 197, 94, 0.12); color: #22c55e; }
.bug-report-notice--error { background: rgba(239, 68, 68, 0.12); color: #ef4444; }

.bug-report-form { display: flex; flex-direction: column; gap: 20px; }
.bug-report-field { display: flex; flex-direction: column; gap: 6px; }
.bug-report-field span { font-size: 0.8125rem; font-weight: 600; color: var(--aurora-text); }
.bug-report-field textarea {
  padding: 10px 12px; border-radius: var(--aurora-radius-sm); border: 1px solid var(--aurora-border);
  background: var(--aurora-bg-subtle); color: var(--aurora-text); font-size: 0.875rem;
  font-family: inherit; resize: vertical;
}

.bug-report-diagnostics {
  background: var(--aurora-bg-subtle); border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-md); padding: 14px 16px;
}
.bug-report-diagnostics h2 {
  margin: 0 0 10px; font-size: 0.75rem; text-transform: uppercase;
  letter-spacing: 0.03em; color: var(--aurora-text-muted); font-weight: 600;
}
.bug-report-diagnostics dl { margin: 0; display: flex; flex-direction: column; gap: 6px; }
.bug-report-diagnostics dl > div { display: flex; justify-content: space-between; font-size: 0.8125rem; }
.bug-report-diagnostics dt { color: var(--aurora-text-muted); }
.bug-report-diagnostics dd { margin: 0; color: var(--aurora-text); font-weight: 500; }

.bug-report-form__actions { display: flex; justify-content: flex-end; }
</style>
