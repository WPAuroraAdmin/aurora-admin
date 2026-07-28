/**
 * Human-readable descriptions for WordPress's capability system, shown as
 * an info tooltip next to each capability checkbox in Role Editor.
 *
 * Two groups: the ~60 named capabilities WordPress core actually checks
 * (edit_posts, manage_options, etc.), and the legacy level_0…level_10
 * "user level" system from WordPress 1.x/early 2.x. Levels predate named
 * capabilities and are deprecated, but WordPress still creates them on
 * every default role for backward compatibility with old themes/plugins
 * that call current_user_can('level_X') or check $user->user_level
 * directly instead of a named capability — core itself hasn't checked a
 * level anywhere for years. They don't map to anything meaningful beyond
 * that compatibility shim, which is exactly why they look unexplained.
 */
const LEVEL_MATCH = /^level_(\d{1,2})$/;

const CORE_CAPABILITIES = {
  read: 'The baseline capability every logged-in user needs just to access wp-admin at all.',
  edit_posts: 'Edit their own posts.',
  edit_others_posts: "Edit posts authored by other users.",
  edit_private_posts: 'Edit private posts (not publicly visible).',
  edit_published_posts: 'Edit posts that have already been published.',
  publish_posts: 'Publish posts, rather than only saving them as drafts.',
  delete_posts: 'Delete their own posts.',
  delete_others_posts: "Delete posts authored by other users.",
  delete_private_posts: 'Delete private posts.',
  delete_published_posts: 'Delete posts that have already been published.',
  read_private_posts: "View private posts that aren't publicly visible.",

  edit_pages: 'Edit their own pages.',
  edit_others_pages: "Edit pages authored by other users.",
  edit_private_pages: 'Edit private pages.',
  edit_published_pages: 'Edit pages that have already been published.',
  publish_pages: 'Publish pages, rather than only saving them as drafts.',
  delete_pages: 'Delete their own pages.',
  delete_others_pages: "Delete pages authored by other users.",
  delete_private_pages: 'Delete private pages.',
  delete_published_pages: 'Delete pages that have already been published.',
  read_private_pages: "View private pages that aren't publicly visible.",

  moderate_comments: 'Approve, edit, and delete comments awaiting moderation.',
  manage_categories: 'Create, edit, and delete post categories and tags.',
  manage_links: 'Manage the legacy Links (blogroll) feature.',
  upload_files: 'Upload files to the Media Library.',

  list_users: 'View the list of registered users.',
  create_users: 'Create new user accounts.',
  edit_users: "Edit other users' profiles and roles.",
  delete_users: 'Delete other user accounts.',
  promote_users: "Change other users' roles.",
  remove_users: 'Remove other users from a site (multisite: from this one site, not delete the account entirely).',

  manage_options: "Change site-wide settings — one of the broadest capabilities short of being a full administrator.",
  edit_theme_options: 'Change theme settings such as menus, widgets, and the Customizer.',
  switch_themes: 'Activate a different installed theme.',
  install_themes: 'Install new themes from the WordPress theme directory or by file upload.',
  update_themes: 'Update installed themes to a newer version.',
  delete_themes: 'Delete theme files from the site.',
  edit_themes: 'Edit theme source code directly from the admin — high risk, equivalent to direct server file access.',

  activate_plugins: 'Activate and deactivate plugins.',
  install_plugins: 'Install new plugins from the WordPress plugin directory or by file upload.',
  update_plugins: 'Update installed plugins to a newer version.',
  delete_plugins: 'Delete plugin files from the site.',
  edit_plugins: 'Edit plugin source code directly from the admin — high risk, equivalent to direct server file access.',

  edit_files: 'Edit plugin and theme files directly from the admin (Theme/Plugin Editor) — high risk, equivalent to direct server file access.',
  edit_dashboard: "Customize the admin dashboard's own widgets.",
  update_core: 'Update WordPress core to a newer version.',
  import: 'Import content into the site from an external source (Tools › Import).',
  export: 'Export site content to a file (Tools › Export).',
  customize: 'Access the Theme Customizer.',

  unfiltered_html: 'Post raw, unfiltered HTML and JavaScript in content — a real cross-site-scripting (XSS) risk if granted to anyone other than a fully trusted administrator.',
  unfiltered_upload: "Upload any file type without WordPress's normal file-type restrictions — a real security risk; only Administrators have this by default, and only on single-site installs.",

  manage_network: '(Multisite) Access the network admin dashboard.',
  manage_sites: '(Multisite) Create, edit, and delete individual sites on the network.',
  manage_network_users: '(Multisite) Manage user accounts network-wide.',
  manage_network_plugins: '(Multisite) Manage plugins network-wide.',
  manage_network_themes: '(Multisite) Manage themes network-wide.',
  manage_network_options: '(Multisite) Change network-wide settings.',
  delete_site: '(Multisite) Delete this entire site.',
  create_sites: '(Multisite) Create new sites on the network.',
};

export const describeCapability = (cap) => {
  const levelMatch = cap.match(LEVEL_MATCH);
  if (levelMatch) {
    const n = Number(levelMatch[1]);
    return (
      `Legacy WordPress "user level" ${n} (0–10 scale, 10 highest) from the old pre-2.0 permissions ` +
      "system. Deprecated in favor of the named capabilities below, but WordPress still assigns the " +
      "full set of levels a role qualifies for, purely so old themes/plugins that check " +
      `current_user_can('level_${n}') or a user's numeric level directly still work. Core itself hasn't ` +
      'checked a level in years — safe to leave alone; removing it only affects compatibility with very old code.'
    );
  }

  return CORE_CAPABILITIES[cap] || 'Custom capability — not part of WordPress core. Likely added by a plugin or theme.';
};
