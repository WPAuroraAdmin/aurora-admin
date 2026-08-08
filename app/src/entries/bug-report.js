import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import BugReport from '@/pages/bug-report/BugReport.vue';

const mountEl = document.getElementById('aurora-admin-bug-report-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(BugReport, { serverData: data }).mount(mountEl);
}
