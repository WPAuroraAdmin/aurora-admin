<?php
namespace AuroraAdmin\Pages;

defined("ABSPATH") || exit();

/**
 * Registers the Appearance-area screens for the "full admin takeover"
 * initiative: Nav Menus, Themes, Widgets (all real hijacked replacements).
 *
 * Theme Editor is deliberately NOT hijacked or replaced — arbitrary
 * in-browser PHP/CSS file editing is exactly the category of functionality
 * WordPress.org's Plugin Directory policy excludes (see the removed
 * File Manager sub-app, now distributed as a separate standalone plugin
 * instead of being bundled here). Visits to theme-editor.php fall through
 * to WordPress core's own Theme Editor unmodified, which already respects
 * DISALLOW_FILE_EDIT itself.
 */
class AppearancePages
{
  public function __construct()
  {
    ScreenHijack::register(
      "load-nav-menus.php",
      "edit_theme_options",
      "full_admin_takeover",
      "nav-menus",
      "aurora-admin-nav-menus-root"
    );

    ScreenHijack::register(
      "load-themes.php",
      "switch_themes",
      "full_admin_takeover",
      "themes",
      "aurora-admin-themes-root",
      null,
      null,
      function () {
        return [
          "customizeUrl" => esc_url_raw(admin_url("customize.php")),
          // Native theme-install.php covers both "browse WordPress.org" and
          // "Upload Theme" (a button at the top of that same screen) — not
          // rebuilt here, same reuse-native-flow-for-add pattern as Plugins'
          // addUrl (plugin-install.php) and Media's newMediaUrl
          // (media-new.php).
          "addThemeUrl" => current_user_can("install_themes") ? esc_url_raw(admin_url("theme-install.php")) : "",
        ];
      }
    );

    ScreenHijack::register(
      "load-widgets.php",
      "edit_theme_options",
      "full_admin_takeover",
      "widgets",
      "aurora-admin-widgets-root"
    );
  }
}
