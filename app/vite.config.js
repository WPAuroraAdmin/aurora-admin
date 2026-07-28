import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

// Multi-entry build: one entry per admin screen (see rollupOptions.input).
// Each is enqueued as its own ES module by admin/src/Utility/Assets.php,
// which also walks the manifest's `imports` chain for shared-chunk CSS.
// Rollup automatically splits code shared by 2+ entries into its own chunk;
// `vue` is pinned to a named `vendor-vue` chunk below so its hash stays
// stable across app-code changes (returning users keep it cached across
// plugin updates instead of re-downloading it every release).
//
// Sourcemaps are on by default for local debugging (the dev loop hot-patches
// app/dist into a LocalWP install). The distribution build sets
// AURORA_SOURCEMAP=false so the shipped zip doesn't carry ~1.2 MB of .map
// files that are useless to end users.
const emitSourcemaps = process.env.AURORA_SOURCEMAP !== 'false';

export default defineConfig({
  root: fileURLToPath(new URL('./src', import.meta.url)),
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  build: {
    outDir: fileURLToPath(new URL('./dist', import.meta.url)),
    emptyOutDir: true,
    sourcemap: emitSourcemaps,
    manifest: true,
    rollupOptions: {
      input: {
        shell: fileURLToPath(new URL('./src/entries/shell.js', import.meta.url)),
        dashboard: fileURLToPath(new URL('./src/entries/dashboard.js', import.meta.url)),
        settings: fileURLToPath(new URL('./src/entries/settings.js', import.meta.url)),
        'setup-wizard': fileURLToPath(new URL('./src/entries/setup-wizard.js', import.meta.url)),
        'menu-creator': fileURLToPath(new URL('./src/entries/menu-creator.js', import.meta.url)),
        modules: fileURLToPath(new URL('./src/entries/modules.js', import.meta.url)),
        'admin-notices': fileURLToPath(new URL('./src/entries/admin-notices.js', import.meta.url)),
        'role-editor': fileURLToPath(new URL('./src/entries/role-editor.js', import.meta.url)),
        'activity-log': fileURLToPath(new URL('./src/entries/activity-log.js', import.meta.url)),
        plugins: fileURLToPath(new URL('./src/entries/plugins.js', import.meta.url)),
        comments: fileURLToPath(new URL('./src/entries/comments.js', import.meta.url)),
        posts: fileURLToPath(new URL('./src/entries/posts.js', import.meta.url)),
        pages: fileURLToPath(new URL('./src/entries/pages.js', import.meta.url)),
        media: fileURLToPath(new URL('./src/entries/media.js', import.meta.url)),
        users: fileURLToPath(new URL('./src/entries/users.js', import.meta.url)),
        'native-settings': fileURLToPath(new URL('./src/entries/native-settings.js', import.meta.url)),
        'media-upload': fileURLToPath(new URL('./src/entries/media-upload.js', import.meta.url)),
        'add-user': fileURLToPath(new URL('./src/entries/add-user.js', import.meta.url)),
        'nav-menus': fileURLToPath(new URL('./src/entries/nav-menus.js', import.meta.url)),
        'themes': fileURLToPath(new URL('./src/entries/themes.js', import.meta.url)),
        'widgets': fileURLToPath(new URL('./src/entries/widgets.js', import.meta.url)),
        'tools': fileURLToPath(new URL('./src/entries/tools.js', import.meta.url)),
        'export': fileURLToPath(new URL('./src/entries/export.js', import.meta.url)),
        'import': fileURLToPath(new URL('./src/entries/import.js', import.meta.url)),
        'profile': fileURLToPath(new URL('./src/entries/profile.js', import.meta.url)),
        'bug-report': fileURLToPath(new URL('./src/entries/bug-report.js', import.meta.url)),
      },
      output: {
        // Keep Vue in its own long-lived vendor chunk (see header note).
        manualChunks: { 'vendor-vue': ['vue'] },
        entryFileNames: '[name].[hash].js',
        chunkFileNames: 'assets/[name].[hash].js',
        assetFileNames: 'assets/[name].[hash][extname]',
      },
    },
  },
});
