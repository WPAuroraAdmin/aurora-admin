import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import Modules from '@/pages/modules/Modules.vue';

const mountEl = document.getElementById('aurora-admin-modules-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(Modules, { serverData: data }).mount(mountEl);
}
