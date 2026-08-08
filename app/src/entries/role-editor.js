import { createApp } from 'vue';
import '@/shell/shell-frame.css';
import RoleEditor from '@/pages/role-editor/RoleEditor.vue';

const mountEl = document.getElementById('aurora-admin-role-editor-root');

if (mountEl) {
  const data = JSON.parse(mountEl.dataset.auroraAdmin || '{}');
  createApp(RoleEditor, { serverData: data }).mount(mountEl);
}
