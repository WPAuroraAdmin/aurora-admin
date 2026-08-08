import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import Themes from '@/pages/themes/Themes.vue';

const mountEl = document.getElementById('aurora-admin-themes-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(Themes, { serverData: data }).mount(mountEl);
}
