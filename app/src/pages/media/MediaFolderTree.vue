<script setup>
import { ref } from 'vue';

const props = defineProps({
  nodes: { type: Array, required: true },
  activeFolderId: { type: Number, required: true },
  expanded: { type: Object, required: true },
});

const emit = defineEmits(['select', 'toggle', 'create-child', 'rename', 'delete', 'reparent', 'assign']);

// Drag-and-drop reparenting (folder onto folder) and media-into-folder both
// go through the same 'text/plain' payload, prefixed so drop handlers can
// tell them apart — custom MIME types are unreliable to read during
// dragover in some browsers, plain text is not.
const dragOverId = ref(0);

const onDragStart = (event, node) => {
  event.dataTransfer.setData('text/plain', `aurora-folder:${node.id}`);
  event.dataTransfer.effectAllowed = 'move';
};

const onDragOver = (event, node) => {
  event.preventDefault();
  dragOverId.value = node.id;
};

const onDragLeave = (node) => {
  if (dragOverId.value === node.id) dragOverId.value = 0;
};

const onDrop = (event, node) => {
  event.preventDefault();
  dragOverId.value = 0;
  const payload = event.dataTransfer.getData('text/plain');
  if (!payload) return;

  if (payload.startsWith('aurora-folder:')) {
    const draggedId = Number(payload.split(':')[1]);
    if (draggedId && draggedId !== node.id) {
      emit('reparent', { id: draggedId, parent: node.id });
    }
  } else if (payload.startsWith('aurora-attachments:')) {
    const ids = payload
      .split(':')[1]
      .split(',')
      .map(Number)
      .filter(Boolean);
    if (ids.length) {
      emit('assign', { folderId: node.id, attachmentIds: ids });
    }
  }
};

const contextMenu = ref({ open: false, x: 0, y: 0, node: null });

const openMenu = (event, node) => {
  contextMenu.value = { open: true, x: event.clientX, y: event.clientY, node };
  setTimeout(() => document.addEventListener('click', closeMenu), 0);
};

const closeMenu = () => {
  contextMenu.value.open = false;
  document.removeEventListener('click', closeMenu);
};

const runFromMenu = (fn) => {
  closeMenu();
  fn();
};
</script>

<template>
  <ul class="mf-tree">
    <li v-for="node in nodes" :key="node.id">
      <div
        class="mf-tree__row"
        :class="{ 'mf-tree__row--active': node.id === activeFolderId, 'mf-tree__row--dragover': dragOverId === node.id }"
        draggable="true"
        @dragstart="onDragStart($event, node)"
        @dragover="onDragOver($event, node)"
        @dragleave="onDragLeave(node)"
        @drop="onDrop($event, node)"
        @contextmenu.prevent="openMenu($event, node)"
      >
        <button
          v-if="node.children && node.children.length"
          type="button"
          class="mf-tree__toggle"
          @click="emit('toggle', node.id)"
        >
          <span class="dashicons dashicons-arrow-right-alt2" :class="{ 'mf-tree__toggle-icon--open': expanded[node.id] }" />
        </button>
        <span v-else class="mf-tree__toggle-spacer" />
        <button type="button" class="mf-tree__label" @click="emit('select', node.id)">
          <span class="dashicons dashicons-portfolio" :style="node.color ? { color: node.color } : {}" />
          <span class="mf-tree__name">{{ node.name }}</span>
          <span class="mf-tree__count">{{ node.count }}</span>
        </button>
      </div>

      <MediaFolderTree
        v-if="expanded[node.id] && node.children && node.children.length"
        :nodes="node.children"
        :active-folder-id="activeFolderId"
        :expanded="expanded"
        @select="emit('select', $event)"
        @toggle="emit('toggle', $event)"
        @create-child="emit('create-child', $event)"
        @rename="emit('rename', $event)"
        @delete="emit('delete', $event)"
        @reparent="emit('reparent', $event)"
        @assign="emit('assign', $event)"
      />
    </li>
  </ul>

  <Teleport to="body">
    <ul v-if="contextMenu.open" class="mf-context-menu" :style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }" @contextmenu.prevent>
      <li><button type="button" @click="runFromMenu(() => emit('create-child', contextMenu.node.id))"><span class="dashicons dashicons-plus" /> New Subfolder</button></li>
      <li><button type="button" @click="runFromMenu(() => emit('rename', contextMenu.node))"><span class="dashicons dashicons-edit" /> Rename</button></li>
      <li class="mf-context-menu__sep" />
      <li><button type="button" class="mf-context-menu__danger" @click="runFromMenu(() => emit('delete', contextMenu.node))"><span class="dashicons dashicons-trash" /> Delete</button></li>
    </ul>
  </Teleport>
</template>

<style scoped>
.mf-tree { list-style: none; margin: 0; padding-left: 14px; }
.mf-tree:first-of-type { padding-left: 0; }
.mf-tree__row {
  display: flex; align-items: center; gap: 2px; border-radius: var(--aurora-radius-sm);
}
.mf-tree__row:hover { background: var(--aurora-bg-subtle); }
.mf-tree__row--active { background: var(--aurora-accent-soft); }
.mf-tree__row--dragover { outline: 2px dashed var(--aurora-accent); outline-offset: -2px; }
.mf-tree__toggle,
.mf-tree__toggle-spacer {
  width: 18px; height: 24px; flex-shrink: 0;
}
.mf-tree__toggle {
  border: none; background: none; cursor: pointer;
  color: var(--aurora-text-muted); display: inline-flex; align-items: center; justify-content: center;
}
.mf-tree__toggle .dashicons { font-size: 14px; width: 14px; height: 14px; transition: transform 0.15s; }
.mf-tree__toggle-icon--open { transform: rotate(90deg); }
.mf-tree__label {
  flex: 1; min-width: 0; display: flex; align-items: center; gap: 6px;
  border: none; background: none; cursor: pointer; text-align: left;
  padding: 3px 4px; color: var(--aurora-text); font-size: 0.8125rem;
}
.mf-tree__name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
.mf-tree__label .dashicons:first-child { color: var(--aurora-text-muted); flex-shrink: 0; font-size: 15px; width: 15px; height: 15px; }
.mf-tree__count { font-size: 0.7rem; color: var(--aurora-text-muted); flex-shrink: 0; }
</style>

<style>
.mf-context-menu {
  position: fixed;
  z-index: 100000;
  min-width: 170px;
  margin: 0;
  padding: 4px;
  list-style: none;
  border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-sm);
  background: var(--aurora-frame-bg);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
}
.mf-context-menu button {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 7px 10px;
  border: none;
  background: none;
  border-radius: var(--aurora-radius-sm);
  color: var(--aurora-text);
  font-size: 0.8rem;
  text-align: left;
  cursor: pointer;
}
.mf-context-menu button:hover { background: var(--aurora-bg-subtle); }
.mf-context-menu button .dashicons { font-size: 16px; width: 16px; height: 16px; color: var(--aurora-text-muted); }
.mf-context-menu__danger { color: #ef4444 !important; }
.mf-context-menu__danger .dashicons { color: #ef4444 !important; }
.mf-context-menu__sep {
  height: 1px;
  margin: 4px 6px;
  background: var(--aurora-border);
}
</style>
