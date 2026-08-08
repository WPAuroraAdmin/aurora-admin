import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import SettingsPage from '@/pages/settings/Settings.vue';

const mountEl = document.getElementById('aurora-admin-settings-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(SettingsPage, { serverData: data }).mount(mountEl);
}
