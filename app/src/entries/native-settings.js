import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import NativeSettings from '@/pages/native-settings/NativeSettings.vue';

const mountEl = document.getElementById('aurora-admin-native-settings-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(NativeSettings, { serverData: data }).mount(mountEl);
}
