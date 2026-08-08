import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import Posts from '@/pages/posts/Posts.vue';

const mountEl = document.getElementById('aurora-admin-posts-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(Posts, { serverData: data }).mount(mountEl);
}
