import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import Export from '@/pages/export/Export.vue';

const mountEl = document.getElementById('aurora-admin-export-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(Export, { serverData: data }).mount(mountEl);
}
