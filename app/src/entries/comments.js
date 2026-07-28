import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import Comments from '@/pages/comments/Comments.vue';

const mountEl = document.getElementById('aurora-admin-comments-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(Comments, { serverData: data }).mount(mountEl);
}
