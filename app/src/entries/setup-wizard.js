import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import SetupWizard from '@/pages/setup-wizard/SetupWizard.vue';

const mountEl = document.getElementById('aurora-admin-setup-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(SetupWizard, { serverData: data }).mount(mountEl);
}
