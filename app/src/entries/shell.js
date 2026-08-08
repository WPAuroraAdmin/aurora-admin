import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import SiteShell from '@/shell/SiteShell.vue';

const mountEl = document.getElementById('aurora-admin-shell-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(SiteShell, { serverData: data }).mount(mountEl);
}
