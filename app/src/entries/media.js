import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import Media from '@/pages/media/Media.vue';

const mountEl = document.getElementById('aurora-admin-media-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(Media, { serverData: data }).mount(mountEl);
}
