<script setup>
import { reactive, ref } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
});

const form = reactive({
  username: '',
  email: '',
  first_name: '',
  last_name: '',
  password: '',
  roles: [props.serverData.defaultRole || 'subscriber'],
});

const saving = ref(false);
const error = ref('');
const success = ref('');

const generatePassword = () => {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
  form.password = Array.from({ length: 16 }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
};

const submit = async () => {
  error.value = '';
  success.value = '';
  if (!form.username || !form.email) {
    error.value = 'Username and email are required.';
    return;
  }

  saving.value = true;
  try {
    const res = await fetch(`${props.serverData.restUrl}wp/v2/users`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': props.serverData.restNonce },
      body: JSON.stringify({ ...form, password: form.password || undefined }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data?.message || 'Could not create the user.');

    success.value = `User "${data.username || form.username}" created.`;
    form.username = '';
    form.email = '';
    form.first_name = '';
    form.last_name = '';
    form.password = '';
  } catch (e) {
    error.value = e.message || 'Could not create the user.';
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <main class="add-user-page">
    <header class="add-user-page__header">
      <div>
        <p class="add-user-page__eyebrow">Accounts</p>
        <h1>Add New User</h1>
      </div>
      <a class="button" :href="serverData.usersUrl">Back to Users</a>
    </header>

    <div v-if="success" class="add-user-notice add-user-notice--success">{{ success }}</div>
    <div v-if="error" class="add-user-notice add-user-notice--error">{{ error }}</div>

    <form class="add-user-form" @submit.prevent="submit">
      <label class="add-user-field">
        <span>Username <em>(required)</em></span>
        <input v-model="form.username" type="text" autocomplete="off" />
      </label>

      <label class="add-user-field">
        <span>Email <em>(required)</em></span>
        <input v-model="form.email" type="email" autocomplete="off" />
      </label>

      <label class="add-user-field">
        <span>First Name</span>
        <input v-model="form.first_name" type="text" />
      </label>

      <label class="add-user-field">
        <span>Last Name</span>
        <input v-model="form.last_name" type="text" />
      </label>

      <label class="add-user-field">
        <span>Password</span>
        <div class="add-user-field__row">
          <input v-model="form.password" type="text" placeholder="Leave blank to auto-generate" />
          <button type="button" class="button" @click="generatePassword">Generate</button>
        </div>
      </label>

      <label class="add-user-field">
        <span>Role</span>
        <select v-model="form.roles[0]">
          <option v-for="role in serverData.roles" :key="role.value" :value="role.value">{{ role.label }}</option>
        </select>
      </label>

      <div class="add-user-form__actions">
        <button type="submit" class="button-primary" :disabled="saving">
          {{ saving ? 'Adding User…' : 'Add New User' }}
        </button>
      </div>
    </form>
  </main>
</template>

<style scoped>
.add-user-page { max-width: 640px; margin: 0 auto; }
.add-user-page__header {
  display: flex; align-items: flex-start; justify-content: space-between;
  margin-bottom: 20px;
}
.add-user-page__eyebrow {
  margin: 0 0 4px; font-size: 0.75rem; text-transform: uppercase;
  letter-spacing: 0.06em; color: var(--aurora-text-muted);
}
.add-user-page h1 { margin: 0; font-size: 1.6rem; font-weight: 700; color: var(--aurora-text); }

.add-user-notice {
  padding: 10px 14px; border-radius: var(--aurora-radius-sm); margin-bottom: 16px; font-size: 0.875rem;
}
.add-user-notice--success { background: rgba(34, 197, 94, 0.12); color: #22c55e; }
.add-user-notice--error { background: rgba(239, 68, 68, 0.12); color: #ef4444; }

.add-user-form {
  display: flex; flex-direction: column; gap: 18px;
  background: var(--aurora-bg-subtle);
  border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-md);
  padding: 24px;
}
.add-user-field { display: flex; flex-direction: column; gap: 6px; }
.add-user-field span { font-size: 0.8125rem; font-weight: 600; color: var(--aurora-text); }
.add-user-field em { font-weight: 400; font-style: normal; color: var(--aurora-text-muted); }
.add-user-field input,
.add-user-field select {
  padding: 9px 12px; border-radius: var(--aurora-radius-sm); border: 1px solid var(--aurora-border);
  background: var(--aurora-bg); color: var(--aurora-text); font-size: 0.875rem;
}
.add-user-field__row { display: flex; gap: 8px; }
.add-user-field__row input { flex: 1; }
.add-user-form__actions { margin-top: 4px; }
</style>
