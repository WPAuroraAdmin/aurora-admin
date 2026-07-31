=== Aurora Admin ===
Contributors: Aurora Dragon Studio, NixReaper
Tags: admin, dashboard, admin theme, dark mode, admin redesign
Requires at least: 5.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Source: https://github.com/WPAuroraAdmin/aurora-admin

A modern, fast redesign of the WordPress admin — real replacement screens for Posts, Pages, Media, Users, and more, plus a full dark mode, all free.

== Description ==

Aurora Admin replaces the default WordPress admin with a faster, cleaner interface — without touching how WordPress core actually works underneath. Every screen is a real, functional replacement (not a CSS reskin over the same slow markup): the same data, the same capabilities, the same URLs, just rebuilt.

Full, unminified source (PHP and the Vue admin app) is developed in the open at [github.com/WPAuroraAdmin/aurora-admin](https://github.com/WPAuroraAdmin/aurora-admin). The release ZIP ships the compiled admin app; the uncompiled Vue source and build tooling are in that repository.

**Real replacement screens**

* Posts, Pages, Media Library, Users, and Comments — full list/edit/delete flows
* Nav Menus, Widgets, Themes, and the core Settings screens (General, Writing, Reading, Discussion, Permalinks)
* Your profile page and the block editor get the same modern frame

**Power tools**

* Role Editor — visual capability editor for every role, with plain-English tooltips explaining what each capability actually does
* Activity Log — see who changed what, and when
* Media Folders — organize your Media Library into folders, drag-and-drop included
* Menu Creator — build and customize your own wp-admin sidebar menu
* Admin Notices — control which plugin nag notices you actually see
* White Label — rename/rebrand the admin for client sites

**Look and feel**

* A real dark mode covering the entire admin, not just a few screens
* 10 color presets plus 2 flat neutrals, applied across the whole interface
* Rounded, framed content cards with theme-tinted scrollbars; the block editor and other full-screen views get the same treatment
* Optional Google Font for the interface typeface (off by default — see Privacy below)

Everything in Aurora Admin is free — there's no license key, no locked features, and no upsell.

**Aurora File Manager, Aurora Database Explorer, and Aurora Site Backup (separate plugins)**

File Manager (browse/edit/manage your site's files from wp-admin), Database Explorer (browse your database tables and run read-only queries), and Site Backup (full site backup/restore, optionally to your own S3-compatible or Google Drive storage) are distributed as their own standalone plugins — [Aurora File Manager](https://aurora.auroradragon.studio/file-manager), [Aurora Database Explorer](https://aurora.auroradragon.studio/database-explorer), and [Aurora Site Backup](https://aurora.auroradragon.studio/site-backup) — each installable with one click from Aurora's Modules screen. File-management/code-editing and direct database-query functionality of this kind isn't accepted into the WordPress.org Plugin Directory (see the [Plugin Developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/#are-there-plugins-you-dont-accept)); Site Backup is separate for the same class of reason, since restoring a backup means writing arbitrary files back into the site. Aurora Admin itself never contains any of that functionality.

= Privacy / external services =

The optional "Report a Bug" screen sends your bug description and basic diagnostics (plugin version, WordPress version, PHP version, active theme name, and your site's domain) to a feedback endpoint on auroraplugins.com when you choose to submit it. Nothing is sent automatically or in the background — this only happens if you fill out and submit that form yourself.

The interface font setting (Settings → Appearance) is empty by default, in which case Aurora uses your system font and makes no external requests. If you choose a Google Font, that font file is loaded from Google Fonts (fonts.googleapis.com) on admin pages, which sends a request to Google's servers. Leave the setting empty to avoid any external font requests.

The Modules page's "Install & Activate" buttons for Aurora File Manager, Aurora Database Explorer, and Aurora Site Backup download that plugin's zip file from aurora.auroradragon.studio using WordPress's own plugin-installer API, the same mechanism the native Plugins → Add New screen uses for WordPress.org-hosted plugins. This only happens when you click one of those buttons — nothing is downloaded automatically or in the background. (Aurora Site Backup's own remote-storage options, if you install and configure it, are documented in that plugin's own readme.)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/aurora-admin`, or install it directly through the Plugins screen in WordPress.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Visit the new "Aurora" menu item in your admin sidebar to explore settings and enable/disable individual replacement screens.

== Frequently Asked Questions ==

= Does this replace WordPress core, or is it a theme? =

Neither. Aurora Admin is a plugin that replaces individual wp-admin screens in place — the URLs don't change, and disabling the plugin returns you to the native WordPress screens exactly as they were.

= Is this really free, no paid tiers? =

Yes. Every feature listed above is available with no license key and no restrictions.

= Will this work with my other plugins? =

Aurora Admin only replaces the built-in WordPress admin screens it explicitly targets (Posts, Pages, Media, Users, Comments, Nav Menus, Widgets, Themes, Tools, Export, Import, core Settings screens, Profile, the block editor frame). Running an already-active importer on the Import screen still opens that importer's own native screen, since its content is entirely owned by whichever plugin registered it. Screens added by other plugins are untouched. Each replacement can also be toggled off individually in Aurora's settings if you'd rather keep the native screen for something specific.

= Does Aurora load anything from external servers? =

Not by default. It only makes an external request if you opt in: choosing a Google Font loads it from Google Fonts, submitting the "Report a Bug" form sends it to auroraplugins.com, and the Modules page's install buttons download the companion plugins on click. See "Privacy / external services" above for details.

= Where do I report a bug? =

Use the "Report a Bug" item in the Aurora sidebar menu, or open a thread in this plugin's support forum.

== Screenshots ==

1. The Aurora dashboard.
2. Role Editor with capability tooltips.
3. Media Library with folders, dark mode across the admin.

== Changelog ==

= 1.0 =
* First public release of Aurora Admin.
* Modern admin shell — fixed sidebar, toolbar, and global search — replacing the default WordPress admin chrome.
* Real replacement screens for Dashboard, Posts, Pages, Media, Users, Comments, Plugins, Nav Menus, Widgets, Themes, the core Settings screens (General, Writing, Reading, Discussion, Permalinks), Tools, Export, and Import, plus Add User, Media Upload, and Profile.
* Power tools: Role Editor, an Activity Log with Admin/User tabs and IP tracking, Media Folders, Menu Creator with drag-and-drop reordering, Admin Notices, White Label, Login Redirect, a Disable Gutenberg toggle, and a Modules page for one-click companion-plugin installs.
* Full dark mode — including native screens Aurora frames rather than rebuilds (Site Health, Export/Erase Personal Data, Updates, the Theme/Plugin File Editor) — with 10 color presets plus 2 flat neutrals, rounded framed content cards, theme-tinted scrollbars, and an optional (off-by-default) Google Font for the interface.
* Security & performance hardening, custom admin CSS/JS, and a login-screen customizer.
