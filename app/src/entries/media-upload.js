import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import MediaUpload from '@/pages/media-upload/MediaUpload.vue';

const mountEl = document.getElementById('aurora-admin-media-upload-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(MediaUpload, { serverData: data }).mount(mountEl);
}
