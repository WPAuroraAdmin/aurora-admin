# Changelog

All notable changes to Aurora Admin, newest first — one entry per version bump.

## [1.0] — 2026-07-26

First public release.

### Admin experience
- Full modern admin shell — fixed sidebar, toolbar, and global search — in
  place of the standard WordPress admin chrome.
- Real, functional replacement screens (not visual reskins) for: Dashboard,
  Posts, Pages, Media, Users, Comments, Add User, Media Upload, Profile,
  Plugins, Nav Menus, Widgets, Themes, the five core Settings screens
  (General, Writing, Reading, Discussion, Permalinks), Tools (Available
  Tools), Export, and Import.
- Sub-apps: Aurora Settings, Setup Wizard, Menu Creator (with drag-to-reorder
  and native menu-item preservation), Role Editor, Admin Notices, Activity
  Log, and Report a Bug.
- **Modules**: a one-click addon marketplace for Aurora's standalone
  companion plugins (File Manager, Database Explorer, Site Backup).
- **Login Redirect**: optional, off-by-default redirects — send logged-out
  wp-admin visitors to a custom URL instead of the login screen, and/or send
  specific roles to a custom URL after logging in instead of the dashboard.
  Never touches wp-login.php itself.
- **Disable Gutenberg**: an opt-in toggle that switches every post type to
  WordPress's classic editor instead of the block editor.
- **Activity Log**: a filterable table (Date, Author, IP Address, Type,
  Action, Description) with separate Admin and User tabs; the Author column
  shows the user's avatar, role, and email.
- Media Folders and White Label rebranding for client sites.
- Screens intentionally left native rather than rebuilt: the block editor
  and the Theme/Plugin File Editor — both dark-themed for legibility, since
  full file-editing/code-editor functionality isn't accepted into the
  WordPress.org Plugin Directory.

### Theming
- A real dark mode covering the entire admin, including every native screen
  Aurora frames rather than rebuilds (Site Health, Export/Erase Personal
  Data, Updates, the Theme/Plugin File Editor).
- Live theme presets — 10 color accents plus 2 flat neutrals — applied
  across the whole interface, plus an optional (off-by-default) Google Font
  for the interface typeface.
- Rounded, framed content cards with a soft shadow; scroll areas sit inside
  the rounded frame instead of squaring off its corners, and scrollbars are
  tinted with the active theme accent.

### Under the hood
- REST API namespace `aurora-admin/v1` backing every takeover screen.
- Security & performance hardening module, custom admin CSS/JS injection,
  and a login-screen customizer.

Everything above is free — no license key, no locked features, no upsell.
