import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import Tools from '@/pages/tools/Tools.vue';

const mountEl = document.getElementById('aurora-admin-tools-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(Tools, { serverData: data }).mount(mountEl);
}
