<p align="center">
  <img src="docs/assets/aurora-admin-logo.png" width="180" alt="Aurora Admin logo">
</p>

<h1 align="center">Aurora Admin</h1>

<p align="center">
  <strong>A modern administration platform for WordPress.</strong><br>
  Real replacement screens, native dark mode, powerful workflow tools, and a cleaner way to manage WordPress.
</p>

<p align="center">
  <a href="https://github.com/WPAuroraAdmin/aurora-admin/releases"><img alt="Release" src="https://img.shields.io/github/v/release/WPAuroraAdmin/aurora-admin?style=flat-square"></a>
  <a href="https://wordpress.org"><img alt="WordPress 5.5+" src="https://img.shields.io/badge/WordPress-5.5%2B-21759b?style=flat-square&logo=wordpress"></a>
  <img alt="PHP 7.4+" src="https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php&logoColor=white">
  <a href="LICENSE"><img alt="GPL-2.0-or-later" src="https://img.shields.io/badge/license-GPL--2.0--or--later-7c3aed?style=flat-square"></a>
</p>

<p align="center">
  <a href="#installation">Install</a> ·
  <a href="#features">Features</a> ·
  <a href="https://wpauroraadmin.github.io/aurora-admin/">Website</a> ·
  <a href="CHANGELOG.md">Changelog</a> ·
  <a href="CONTRIBUTING.md">Contribute</a>
</p>

---

## WordPress admin, rebuilt

Aurora Admin replaces the aging WordPress administration experience with fast, functional screens built around the workflows site owners use every day. It preserves WordPress data, permissions, URLs, and compatibility underneath—disable the plugin and the native screens return exactly as they were.

This is not a cosmetic skin over wp-admin. Aurora provides real replacement interfaces for core administration screens while leaving third-party plugin pages untouched.

## Features

| | |
|---|---|
| **Modern admin shell** | Fixed navigation, global search, responsive layout, and a focused workspace. |
| **Real replacement screens** | Dashboard, Posts, Pages, Media, Users, Comments, Plugins, Themes, Settings, Tools, Import, Export, and more. |
| **Native dark mode** | Full-admin coverage, including framed native screens and the block editor. |
| **Analytics dashboard** | Content, engagement, device, media, user, and server-health insights. |
| **Role Editor** | Visual capability management with plain-English descriptions. |
| **Media Folders** | Organize the WordPress Media Library with drag-and-drop folders. |
| **Activity Log** | See administrative and user activity with author, IP, type, action, and description. |
| **Menu Creator** | Rebuild and reorder the wp-admin navigation without losing native items. |
| **White Label** | Tailor the administration experience for client sites. |
| **Modular controls** | Toggle replacement screens and optional features individually. |

## Screens covered

Dashboard · Posts · Pages · Media Library · Media Upload · Users · Add User · Profile · Comments · Plugins · Themes · Nav Menus · Widgets · General Settings · Writing · Reading · Discussion · Permalinks · Tools · Import · Export

## Installation

### From a release

1. Download the latest ZIP from [Releases](https://github.com/WPAuroraAdmin/aurora-admin/releases).
2. In WordPress, open **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP, install it, and activate **Aurora Admin**.
4. Open **Aurora** in the WordPress sidebar and complete the setup wizard.

### Development build

```bash
git clone https://github.com/WPAuroraAdmin/aurora-admin.git
cd aurora-admin/app
npm install
npm run build
```

Place the repository in `wp-content/plugins/aurora-admin`, then activate it from WordPress.

## Requirements

- WordPress 5.5 or newer
- PHP 7.4 or newer
- Node.js and npm only when building the Vue application from source

## Architecture

Aurora Admin uses a PHP integration layer and a Vue/Vite interface:

```text
WordPress Core
├── PHP screen takeovers and services
├── aurora-admin/v1 REST API
└── Vue administration application
    ├── Shared Aurora shell
    ├── Replacement screens
    └── Settings and power tools
```

The plugin keeps WordPress as the source of truth. Existing capabilities, data models, and core actions remain in place.

## Companion plugins

Aurora Admin can install optional standalone modules from its Modules screen:

- Aurora File Manager
- Aurora Database Explorer
- Aurora Site Backup

These are distributed separately because their file, database, and restoration capabilities are outside the scope of the WordPress.org Plugin Directory rules applicable to Aurora Admin itself.

## Privacy

Aurora Admin makes no external request by default. External requests occur only when an administrator explicitly chooses an optional action such as selecting a Google Font, submitting a bug report, or installing a companion module. See [`readme.txt`](readme.txt) for the complete disclosure.

## Contributing

Bug reports and focused pull requests are welcome. Read [`CONTRIBUTING.md`](CONTRIBUTING.md) before submitting changes. Security issues should follow [`SECURITY.md`](SECURITY.md), not a public issue.

## License

Aurora Admin is free software licensed under the [GNU General Public License v2.0 or later](LICENSE).

<p align="center"><strong>Built for a better WordPress administration experience.</strong></p>
