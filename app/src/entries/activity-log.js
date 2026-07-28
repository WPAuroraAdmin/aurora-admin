import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import ActivityLog from '@/pages/activity-log/ActivityLog.vue';

const mountEl = document.getElementById('aurora-admin-activity-log-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(ActivityLog, { serverData: data }).mount(mountEl);
}
