import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import AdminNotices from '@/pages/admin-notices/AdminNotices.vue';

const mountEl = document.getElementById('aurora-admin-admin-notices-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(AdminNotices, { serverData: data }).mount(mountEl);
}
