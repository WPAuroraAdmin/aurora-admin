<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  serverData: { type: Object, required: true },
  collapsed: { type: Boolean, default: false },
});

const open = ref(false);
const root = ref(null);

const profileUrl = computed(() => `${props.serverData.adminUrl}profile.php`);
const settingsUrl = computed(() => `${props.serverData.adminUrl}options-general.php`);

const onDocClick = (e) => {
  if (root.value && !root.value.contains(e.target)) {
    open.value = false;
  }
};
onMounted(() => document.addEventListener('click', onDocClick));
onUnmounted(() => document.removeEventListener('click', onDocClick));
</script>

<template>
  <div ref="root" class="user-menu">
    <div v-if="open" class="user-menu__card">
      <div class="user-menu__header">
        <img v-if="serverData.avatar" :src="serverData.avatar" alt="" class="user-menu__avatar" />
        <div class="user-menu__id">
          <div class="user-menu__name">{{ serverData.userName }}</div>
          <div class="user-menu__email">{{ serverData.userEmail }}</div>
        </div>
      </div>

      <div class="user-menu__items">
        <a :href="profileUrl" class="user-menu__item">
          <span class="dashicons dashicons-admin-users" />
          Edit Profile
        </a>
        <a :href="serverData.siteUrl" target="_blank" rel="noopener noreferrer" class="user-menu__item">
          <span class="dashicons dashicons-admin-home" />
          Visit site
        </a>
        <a :href="settingsUrl" class="user-menu__item">
          <span class="dashicons dashicons-admin-generic" />
          Site Settings
        </a>
        <a :href="serverData.logoutUrl" class="user-menu__item user-menu__item--danger">
          <span class="dashicons dashicons-migrate" />
          Sign Out
        </a>
      </div>
    </div>

    <button
      type="button"
      class="user-menu__trigger"
      :class="{ 'user-menu__trigger--collapsed': collapsed }"
      @click="open = !open"
    >
      <img v-if="serverData.avatar" :src="serverData.avatar" alt="" class="user-menu__trigger-avatar" />
      <span v-if="!collapsed" class="user-menu__trigger-name">{{ serverData.userName }}</span>
      <span v-if="!collapsed" class="dashicons dashicons-arrow-up-alt2 user-menu__trigger-chevron" />
    </button>
  </div>
</template>

<style scoped>
.user-menu {
  position: relative;
  flex-shrink: 0;
  padding: 8px;
  border-top: 1px solid var(--aurora-border);
}

.user-menu__trigger {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  border: none;
  background: none;
  cursor: pointer;
  padding: 6px 8px;
  border-radius: var(--aurora-radius-sm);
  color: var(--aurora-text);
}
.user-menu__trigger:hover {
  background: var(--aurora-bg-subtle);
}

.user-menu__trigger--collapsed {
  justify-content: center;
  padding: 6px 0;
}

.user-menu__trigger-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  flex-shrink: 0;
}

.user-menu__trigger-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.8125rem;
  text-align: left;
}

.user-menu__trigger-chevron {
  font-size: 14px;
  width: 14px;
  height: 14px;
  color: var(--aurora-text-muted);
}

.user-menu__card {
  position: absolute;
  bottom: calc(100% + 8px);
  left: 8px;
  width: 240px;
  background: var(--aurora-bg);
  border: 1px solid var(--aurora-border);
  border-radius: var(--aurora-radius-md);
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.18);
  overflow: hidden;
  z-index: 100000;
}

.user-menu__header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px;
  border-bottom: 1px solid var(--aurora-border);
}
.user-menu__avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  flex-shrink: 0;
}
.user-menu__id { min-width: 0; }
.user-menu__name {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--aurora-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.user-menu__email {
  font-size: 0.75rem;
  color: var(--aurora-text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-menu__items {
  padding: 6px;
  border-bottom: 1px solid var(--aurora-border);
}
.user-menu__item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: var(--aurora-radius-sm);
  text-decoration: none;
  color: var(--aurora-text);
  font-size: 0.8125rem;
}
.user-menu__item:hover {
  background: var(--aurora-bg-subtle);
}
.user-menu__item .dashicons {
  font-size: 16px;
  width: 16px;
  height: 16px;
  color: var(--aurora-text-muted);
}
.user-menu__item--danger {
  color: #ef4444;
}
.user-menu__item--danger .dashicons {
  color: #ef4444;
}

</style>
