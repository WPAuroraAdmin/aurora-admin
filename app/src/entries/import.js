import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import Import from '@/pages/import/Import.vue';

const mountEl = document.getElementById('aurora-admin-import-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(Import, { serverData: data }).mount(mountEl);
}
