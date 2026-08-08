import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import Users from '@/pages/users/Users.vue';

const mountEl = document.getElementById('aurora-admin-users-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(Users, { serverData: data }).mount(mountEl);
}
