<script setup>
import { reactive, ref, onMounted } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const headers = () => ({ 'Content-Type': 'application/json', 'X-WP-Nonce': props.serverData.restNonce });
const meUrl = `${props.serverData.restUrl}wp/v2/users/me?context=edit`;

const form = reactive({
  first_name: '',
  last_name: '',
  nickname: '',
  email: '',
  url: '',
  description: '',
});

const loading = ref(true);
const saving = ref(false);
const notice = ref('');
const error = ref('');

const load = async () => {
  loading.value = true;
  try {
    const res = await fetch(meUrl, { headers: headers() });
    const data = await res.json();
    if (!res.ok) throw new Error(data?.message || 'Could not load your profile.');
    form.first_name = data.first_name || '';
    form.last_name = data.last_name || '';
    form.nickname = data.nickname || '';
    form.email = data.email || '';
    form.url = data.url || '';
    form.description = data.description || '';
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
};

onMounted(load);

const save = async () => {
  saving.value = true;
  error.value = '';
  notice.value = '';
  try {
    const res = await fetch(meUrl, {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify(form),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data?.message || 'Could not save your profile.');
    notice.value = 'Profile updated.';
    setTimeout(() => (notice.value = ''), 2500);
  } catch (e) {
    error.value = e.message;
  } finally {
    saving.value = false;
  }
};

// Password ----------------------------------------------------------------
const newPassword = ref('');
const changingPassword = ref(false);
const passwordNotice = ref('');
const passwordError = ref('');

const generatePassword = () => {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
  newPassword.value = Array.from({ length: 16 }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
};

const changePassword = async () => {
  passwordError.value = '';
  passwordNotice.value = '';
  if (!newPassword.value) return;

  changingPassword.value = true;
  try {
    const res = await fetch(`${props.serverData.restUrl}aurora-admin/v1/profile/password`, {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify({ password: newPassword.value }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data?.message || 'Could not change your password.');
    passwordNotice.value = 'Password updated.';
    newPassword.value = '';
    setTimeout(() => (passwordNotice.value = ''), 2500);
  } catch (e) {
    passwordError.value = e.message;
  } finally {
    changingPassword.value = false;
  }
};
</script>

<template>
  <main class="profile-page">
    <header class="profile-page__header">
      <div>
        <p class="profile-page__eyebrow">Accounts</p>
        <h1>Your Profile</h1>
      </div>
    </header>

    <div v-if="notice" class="profile-notice profile-notice--success">{{ notice }}</div>
    <div v-if="error" class="profile-notice profile-notice--error">{{ error }}</div>

    <p v-if="loading" class="profile-empty">Loading…</p>

    <form v-else class="profile-form" @submit.prevent="save">
      <label class="profile-field">
        <span>First Name</span>
        <input v-model="form.first_name" type="text" />
      </label>
      <label class="profile-field">
        <span>Last Name</span>
        <input v-model="form.last_name" type="text" />
      </label>
      <label class="profile-field">
        <span>Nickname</span>
        <input v-model="form.nickname" type="text" />
      </label>
      <label class="profile-field">
        <span>Email</span>
        <input v-model="form.email" type="email" />
      </label>
      <label class="profile-field">
        <span>Website</span>
        <input v-model="form.url" type="text" />
      </label>
      <label class="profile-field">
        <span>Biographical Info</span>
        <textarea v-model="form.description" rows="4" />
      </label>

      <div class="profile-form__actions">
        <button type="submit" class="button-primary" :disabled="saving">
          {{ saving ? 'Saving…' : 'Update Profile' }}
        </button>
      </div>
    </form>

    <section class="profile-password">
      <h2>Change Password</h2>
      <div v-if="passwordNotice" class="profile-notice profile-notice--success">{{ passwordNotice }}</div>
      <div v-if="passwordError" class="profile-notice profile-notice--error">{{ passwordError }}</div>
      <div class="profile-password__row">
        <input v-model="newPassword" type="text" placeholder="New password" />
        <button type="button" class="button" @click="generatePassword">Generate</button>
        <button type="button" class="button-primary" :disabled="changingPassword || !newPassword" @click="changePassword">
          {{ changingPassword ? 'Updating…' : 'Change Password' }}
        </button>
      </div>
    </section>
  </main>
</template>

<style scoped>
.profile-page { max-width: 640px; margin: 0 auto; }
.profile-page__header { margin-bottom: 16px; }
.profile-page__eyebrow {
  margin: 0 0 4px; font-size: 0.75rem; text-transform: uppercase;
  letter-spacing: 0.06em; color: var(--aurora-text-muted);
}
.profile-page h1 { margin: 0; font-size: 1.6rem; font-weight: 700; color: var(--aurora-text); }
.profile-empty { color: var(--aurora-text-muted); font-size: 0.8125rem; }

.profile-notice { padding: 10px 14px; border-radius: var(--aurora-radius-sm); margin-bottom: 16px; font-size: 0.875rem; }
.profile-notice--success { background: rgba(34, 197, 94, 0.12); color: #22c55e; }
.profile-notice--error { background: rgba(239, 68, 68, 0.12); color: #ef4444; }
.profile-notice--info { background: var(--aurora-bg-subtle); color: var(--aurora-text-muted); }
.profile-notice--info a { color: var(--aurora-accent); }

.profile-form, .profile-password {
  display: flex; flex-direction: column; gap: 18px;
  background: var(--aurora-bg-subtle);
  border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-md);
  padding: 24px;
  margin-bottom: 20px;
}
.profile-field { display: flex; flex-direction: column; gap: 6px; }
.profile-field span { font-size: 0.8125rem; font-weight: 600; color: var(--aurora-text); }
.profile-field input,
.profile-field textarea {
  padding: 9px 12px; border-radius: var(--aurora-radius-sm); border: 1px solid var(--aurora-border);
  background: var(--aurora-bg); color: var(--aurora-text); font-size: 0.875rem;
  font-family: inherit; resize: vertical;
}
.profile-form__actions { margin-top: 4px; }

.profile-password h2 { margin: 0 0 4px; font-size: 0.9375rem; color: var(--aurora-text); }
.profile-password__row { display: flex; gap: 8px; }
.profile-password__row input {
  flex: 1; padding: 9px 12px; border-radius: var(--aurora-radius-sm); border: 1px solid var(--aurora-border);
  background: var(--aurora-bg); color: var(--aurora-text); font-size: 0.875rem;
}
</style>
