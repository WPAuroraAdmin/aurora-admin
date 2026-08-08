import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import Widgets from '@/pages/widgets/Widgets.vue';

const mountEl = document.getElementById('aurora-admin-widgets-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(Widgets, { serverData: data }).mount(mountEl);
}
