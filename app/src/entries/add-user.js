import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import AddUser from '@/pages/users/AddUser.vue';

const mountEl = document.getElementById('aurora-admin-add-user-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(AddUser, { serverData: data }).mount(mountEl);
}
