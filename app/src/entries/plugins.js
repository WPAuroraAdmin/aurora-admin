import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import Plugins from '@/pages/plugins/Plugins.vue';

const mountEl = document.getElementById('aurora-admin-plugins-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(Plugins, { serverData: data }).mount(mountEl);
}
