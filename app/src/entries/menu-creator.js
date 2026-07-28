import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import MenuCreator from '@/pages/menu-creator/MenuCreator.vue';

const mountEl = document.getElementById('aurora-admin-menu-creator-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(MenuCreator, { serverData: data }).mount(mountEl);
}
