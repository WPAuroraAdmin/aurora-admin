<?php
namespace AuroraAdmin\Rest;

defined("ABSPATH") || exit();

/**
 * Installs Aurora's standalone companion plugins (File Manager, Database
 * Explorer, Site Backup, and any future ones) — each split out of Aurora
 * Admin either because WordPress.org's Plugin Directory policy doesn't
 * accept that category of functionality in a directory-listed plugin
 * (File Manager, confirmed via two rejections), or proactively to reduce
 * that same risk (Database Explorer, Site Backup — see
 * PROJECT_STATUS.md's per-plugin sections for the full history). Rather
 * than a bare download link, this wraps WP_Upgrader/Plugin_Upgrader — the
 * same core API the native Plugins > Add New "Install Now" button and the
 * Uploads tab both use — pointed at a fixed, hardcoded HTTPS zip URL per
 * plugin (never client-supplied, so there's no SSRF surface here: the
 * install target isn't user input).
 */
class CompanionPluginData
{
  // Add an entry here for each companion plugin. The REST routes below are
  // generated per-slug (e.g. /companion-plugins/file-manager/status), so a
  // new companion plugin only needs a new entry here plus a matching
  // settingsConfig.js field — no new PHP route-registration code.
  const PLUGINS = [
    "file-manager" => [
      "zip_url" => "https://aurora.auroradragon.studio/aurora-file-manager.zip",
      "plugin_file" => "aurora-file-manager/aurora-file-manager.php",
      "label" => "Aurora File Manager",
    ],
    "database-explorer" => [
      "zip_url" => "https://aurora.auroradragon.studio/aurora-database-explorer.zip",
      "plugin_file" => "aurora-database-explorer/aurora-database-explorer.php",
      "label" => "Aurora Database Explorer",
    ],
    "site-backup" => [
      "zip_url" => "https://aurora.auroradragon.studio/aurora-site-backup.zip",
      "plugin_file" => "aurora-site-backup/aurora-site-backup.php",
      "label" => "Aurora Site Backup",
    ],
  ];

  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    $permission = function () {
      return current_user_can("install_plugins") && current_user_can("activate_plugins");
    };

    foreach (array_keys(self::PLUGINS) as $slug) {
      register_rest_route("aurora-admin/v1", "/companion-plugins/{$slug}/status", [
        "methods" => "GET",
        "callback" => function () use ($slug) {
          return self::status($slug);
        },
        "permission_callback" => $permission,
      ]);

      register_rest_route("aurora-admin/v1", "/companion-plugins/{$slug}/install", [
        "methods" => "POST",
        "callback" => function () use ($slug) {
          return self::install($slug);
        },
        "permission_callback" => $permission,
      ]);
    }
  }

  private static function is_installed($plugin_file)
  {
    if (!function_exists("get_plugins")) {
      require_once ABSPATH . "wp-admin/includes/plugin.php";
    }
    return array_key_exists($plugin_file, get_plugins());
  }

  private static function is_active($plugin_file)
  {
    return is_plugin_active($plugin_file);
  }

  public static function status($slug)
  {
    if (!function_exists("is_plugin_active")) {
      require_once ABSPATH . "wp-admin/includes/plugin.php";
    }
    $plugin_file = self::PLUGINS[$slug]["plugin_file"];
    return new \WP_REST_Response([
      "installed" => self::is_installed($plugin_file),
      "active" => self::is_installed($plugin_file) && self::is_active($plugin_file),
    ], 200);
  }

  /**
   * Installs (if not already present) and activates the given companion
   * plugin. Uses Plugin_Upgrader::install() against the fixed zip URL — the
   * exact same core class WordPress's own plugin-install.php uses, so it
   * inherits the same WP_Filesystem credential handling (a host requiring
   * FTP/SSH credentials for direct file writes will surface that as a
   * normal WP_Error here, not a fatal).
   */
  public static function install($slug)
  {
    $plugin = self::PLUGINS[$slug];
    $plugin_file = $plugin["plugin_file"];

    if (self::is_installed($plugin_file)) {
      if (!self::is_active($plugin_file)) {
        $activated = activate_plugin($plugin_file);
        if (is_wp_error($activated)) {
          return new \WP_REST_Response(["success" => false, "message" => $activated->get_error_message()], 500);
        }
      }
      /* translators: %s: companion plugin name, e.g. "Aurora File Manager" */
      return new \WP_REST_Response(["success" => true, "message" => sprintf(__("%s is installed and active.", "aurora-admin"), $plugin["label"])], 200);
    }

    require_once ABSPATH . "wp-admin/includes/class-wp-upgrader.php";
    require_once ABSPATH . "wp-admin/includes/plugin-install.php";
    require_once ABSPATH . "wp-admin/includes/plugin.php";

    $skin = new \Automatic_Upgrader_Skin();
    $upgrader = new \Plugin_Upgrader($skin);
    $result = $upgrader->install($plugin["zip_url"]);

    if (is_wp_error($result)) {
      return new \WP_REST_Response(["success" => false, "message" => $result->get_error_message()], 500);
    }
    if ($result !== true) {
      $error = $skin->get_errors();
      return new \WP_REST_Response([
        "success" => false,
        "message" => is_wp_error($error) && $error->get_error_message() ? $error->get_error_message() : __("Installation failed.", "aurora-admin"),
      ], 500);
    }

    if (!self::is_installed($plugin_file)) {
      return new \WP_REST_Response(["success" => false, "message" => __("Installed, but the plugin file wasn't found afterward — check Plugins for details.", "aurora-admin")], 500);
    }

    $activated = activate_plugin($plugin_file);
    if (is_wp_error($activated)) {
      return new \WP_REST_Response([
        "success" => true,
        "message" => __("Installed, but couldn't activate automatically — activate it from the Plugins screen.", "aurora-admin"),
      ], 200);
    }

    /* translators: %s: companion plugin name, e.g. "Aurora File Manager" */
    return new \WP_REST_Response(["success" => true, "message" => sprintf(__("%s installed and activated.", "aurora-admin"), $plugin["label"])], 200);
  }
}
