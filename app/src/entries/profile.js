import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import Profile from '@/pages/profile/Profile.vue';

const mountEl = document.getElementById('aurora-admin-profile-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(Profile, { serverData: data }).mount(mountEl);
}
