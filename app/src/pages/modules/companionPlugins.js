/**
 * Aurora's standalone companion plugins, installable from the Modules page.
 * Single source of truth for both the card metadata and the REST endpoints
 * that back them (admin/src/Rest/CompanionPluginData.php) — a new companion
 * plugin only needs an entry here plus a matching PHP-side entry, no other
 * wiring.
 */
export const companionPlugins = [
  {
    slug: 'file-manager',
    icon: 'dashicons-portfolio',
    label: 'Aurora File Manager',
    description:
      'Browse, edit, and manage your site’s files (themes, plugins, uploads, and more) directly from wp-admin.',
    statusEndpoint: '/companion-plugins/file-manager/status',
    installEndpoint: '/companion-plugins/file-manager/install',
    manageUrl: 'admin.php?page=aurora-file-manager',
  },
  {
    slug: 'database-explorer',
    icon: 'dashicons-editor-table',
    label: 'Aurora Database Explorer',
    description:
      'A Supabase-style database browser inside wp-admin — see every table with row counts and sizes, browse paginated data, and run your own read-only queries.',
    statusEndpoint: '/companion-plugins/database-explorer/status',
    installEndpoint: '/companion-plugins/database-explorer/install',
    manageUrl: 'admin.php?page=aurora-database-explorer',
  },
  {
    slug: 'site-backup',
    icon: 'dashicons-migrate',
    label: 'Aurora Site Backup',
    description:
      'Full site backup (database + files) and restore-in-place, on demand or on a recurring schedule, stored locally or on your own S3-compatible or Google Drive remote storage.',
    statusEndpoint: '/companion-plugins/site-backup/status',
    installEndpoint: '/companion-plugins/site-backup/install',
    manageUrl: 'admin.php?page=aurora-site-backup',
  },
];
