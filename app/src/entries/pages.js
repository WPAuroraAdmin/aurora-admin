import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import Pages from '@/pages/pages/Pages.vue';

const mountEl = document.getElementById('aurora-admin-pages-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(Pages, { serverData: data }).mount(mountEl);
}
