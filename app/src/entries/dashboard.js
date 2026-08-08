import { createApp } from 'vue';
// Pulls in the shared --aurora-* theme variables. Harmless if the site-wide
// shell already loaded them (identical values); keeps this page self-sufficient.
import '@/shell/shell-frame.css';
import DashboardPage from '@/pages/dashboard/Dashboard.vue';

const mountEl = document.getElementById('aurora-admin-dashboard-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(DashboardPage, { serverData: data }).mount(mountEl);
}
