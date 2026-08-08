<?php
namespace AuroraAdmin\Rest;

defined("ABSPATH") || exit();

/**
 * Discovers and installs Aurora's standalone companion plugins (File
 * Manager, Database Explorer, Site Backup, and any future ones) — each
 * split out of Aurora Admin either because WordPress.org's Plugin
 * Directory policy doesn't accept that category of functionality in a
 * directory-listed plugin (File Manager, confirmed via two rejections), or
 * proactively to reduce that same risk (Database Explorer, Site Backup —
 * see PROJECT_STATUS.md's per-plugin sections for the full history).
 *
 * The available-modules list is no longer hardcoded here. Instead it's
 * fetched from a small JSON manifest hosted at MANIFEST_URL and cached in
 * a transient — adding, renaming, or removing a companion plugin is then
 * just an edit to that one remote file, no plugin release required. If
 * the manifest is unreachable, empty, or malformed, this fails to an empty
 * list rather than erroring, so the Modules screen simply shows no cards.
 *
 * Every zip_url pulled from the manifest is verified to resolve to
 * MANIFEST_HOST over HTTPS before it's ever passed to the installer —
 * the manifest can only ever point installs at Aurora's own domain, never
 * an arbitrary host, even if a manifest entry were malformed or tampered
 * with in transit.
 *
 * Installation itself wraps WP_Upgrader/Plugin_Upgrader — the same core
 * API the native Plugins > Add New "Install Now" button and the Uploads
 * tab both use — so it inherits the same WP_Filesystem credential
 * handling (a host requiring FTP/SSH credentials for direct file writes
 * surfaces that as a normal WP_Error here, not a fatal).
 */
class CompanionPluginData
{
  const MANIFEST_URL = "https://auroraadmin.dev/modules/modules.json";
  const MANIFEST_HOST = "auroraadmin.dev";
  const CACHE_KEY = "aurora_admin_companion_modules";
  const CACHE_TTL = 12 * HOUR_IN_SECONDS;
  // Short TTL for a failed/empty fetch, so a temporary outage on the
  // manifest host doesn't leave the Modules screen empty for 12 hours.
  const CACHE_TTL_EMPTY = 15 * MINUTE_IN_SECONDS;

  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    $permission = function () {
      return current_user_can("install_plugins") && current_user_can("activate_plugins");
    };

    // One list endpoint (manifest metadata merged with local install
    // status) instead of a fixed route per known plugin — the frontend no
    // longer needs to know the set of slugs in advance.
    register_rest_route("aurora-admin/v1", "/companion-plugins", [
      "methods" => "GET",
      "callback" => [self::class, "list_modules"],
      "permission_callback" => $permission,
    ]);

    register_rest_route("aurora-admin/v1", "/companion-plugins/(?P<slug>[a-z0-9\-]+)/install", [
      "methods" => "POST",
      "callback" => function ($request) {
        return self::install($request->get_param("slug"));
      },
      "permission_callback" => $permission,
    ]);
  }

  /**
   * Fetches, validates, and caches the companion-plugin manifest. Returns
   * an array keyed by slug; entries missing a required field are dropped
   * rather than allowed through partially populated.
   */
  private static function get_manifest()
  {
    $cached = get_transient(self::CACHE_KEY);
    if (is_array($cached)) {
      return $cached;
    }

    $modules = self::fetch_and_validate_manifest();
    set_transient(self::CACHE_KEY, $modules, $modules ? self::CACHE_TTL : self::CACHE_TTL_EMPTY);
    return $modules;
  }

  private static function fetch_and_validate_manifest()
  {
    $response = wp_remote_get(self::MANIFEST_URL, ["timeout" => 8]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
      return [];
    }

    $entries = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($entries)) {
      return [];
    }

    $modules = [];
    foreach ($entries as $entry) {
      $module = self::sanitize_entry($entry);
      if ($module !== null) {
        $modules[$module["slug"]] = $module;
      }
    }
    return $modules;
  }

  private static function sanitize_entry($entry)
  {
    if (!is_array($entry)) {
      return null;
    }

    $slug = isset($entry["slug"]) ? sanitize_key($entry["slug"]) : "";
    $name = isset($entry["name"]) ? sanitize_text_field($entry["name"]) : "";
    $plugin_file = isset($entry["plugin_file"]) ? sanitize_text_field($entry["plugin_file"]) : "";
    $zip_url = isset($entry["zip"]) ? esc_url_raw($entry["zip"]) : "";
    $description = isset($entry["description"]) ? sanitize_text_field($entry["description"]) : "";

    if (!$slug || !$name || !$plugin_file || !$zip_url) {
      return null;
    }

    // Every plugin_file is expected to be "slug-folder/slug-folder.php" —
    // reject anything that isn't a plain relative plugin path (no "..",
    // no leading slash), since this string is later passed straight to
    // activate_plugin()/is_plugin_active().
    if (strpos($plugin_file, "..") !== false || $plugin_file[0] === "/" || substr($plugin_file, -4) !== ".php") {
      return null;
    }

    $host = wp_parse_url($zip_url, PHP_URL_HOST);
    $scheme = wp_parse_url($zip_url, PHP_URL_SCHEME);
    if ($scheme !== "https" || $host !== self::MANIFEST_HOST) {
      return null;
    }

    return [
      "slug" => $slug,
      "name" => $name,
      "description" => $description,
      "plugin_file" => $plugin_file,
      "zip_url" => $zip_url,
    ];
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

  /** GET /companion-plugins — manifest entries merged with local install/active status. */
  public static function list_modules()
  {
    if (!function_exists("is_plugin_active")) {
      require_once ABSPATH . "wp-admin/includes/plugin.php";
    }

    $modules = [];
    foreach (self::get_manifest() as $module) {
      $installed = self::is_installed($module["plugin_file"]);
      $modules[] = [
        "slug" => $module["slug"],
        "name" => $module["name"],
        "description" => $module["description"],
        "installed" => $installed,
        "active" => $installed && self::is_active($module["plugin_file"]),
      ];
    }

    return new \WP_REST_Response($modules, 200);
  }

  /**
   * Installs (if not already present) and activates the given companion
   * plugin. Uses Plugin_Upgrader::install() against the manifest-supplied,
   * host-verified zip URL.
   */
  public static function install($slug)
  {
    $manifest = self::get_manifest();
    if (!isset($manifest[$slug])) {
      return new \WP_REST_Response(["success" => false, "message" => __("This module is no longer available.", "aurora-admin")], 404);
    }
    $plugin = $manifest[$slug];
    $plugin_file = $plugin["plugin_file"];

    if (self::is_installed($plugin_file)) {
      if (!self::is_active($plugin_file)) {
        $activated = activate_plugin($plugin_file);
        if (is_wp_error($activated)) {
          return new \WP_REST_Response(["success" => false, "message" => $activated->get_error_message()], 500);
        }
      }
      /* translators: %s: companion plugin name, e.g. "Aurora File Manager" */
      return new \WP_REST_Response(["success" => true, "message" => sprintf(__("%s is installed and active.", "aurora-admin"), $plugin["name"])], 200);
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
    return new \WP_REST_Response(["success" => true, "message" => sprintf(__("%s installed and activated.", "aurora-admin"), $plugin["name"])], 200);
  }
}
