import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import NavMenus from '@/pages/nav-menus/NavMenus.vue';

const mountEl = document.getElementById('aurora-admin-nav-menus-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(NavMenus, { serverData: data }).mount(mountEl);
}
