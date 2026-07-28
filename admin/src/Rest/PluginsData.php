<?php
namespace AuroraAdmin\Rest;

defined("ABSPATH") || exit();

/**
 * REST endpoints for Aurora's custom Installed Plugins page.
 */
class PluginsData
{
  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/plugins", [
      "methods" => "GET",
      "callback" => [self::class, "list_plugins"],
      "permission_callback" => function () {
        return current_user_can("activate_plugins");
      },
    ]);

    register_rest_route("aurora-admin/v1", "/plugins/activate", [
      "methods" => "POST",
      "callback" => [self::class, "activate_plugin"],
      "permission_callback" => function () {
        return current_user_can("activate_plugins");
      },
      "args" => [
        "file" => ["type" => "string", "required" => true],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/plugins/deactivate", [
      "methods" => "POST",
      "callback" => [self::class, "deactivate_plugin"],
      "permission_callback" => function () {
        return current_user_can("activate_plugins");
      },
      "args" => [
        "file" => ["type" => "string", "required" => true],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/plugins/delete", [
      "methods" => "DELETE",
      "callback" => [self::class, "delete_plugin"],
      "permission_callback" => function () {
        return current_user_can("delete_plugins");
      },
      "args" => [
        "file" => ["type" => "string", "required" => true],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/plugins/update", [
      "methods" => "POST",
      "callback" => [self::class, "update_plugin"],
      "permission_callback" => function () {
        return current_user_can("update_plugins");
      },
      "args" => [
        "file" => ["type" => "string", "required" => true],
      ],
    ]);
  }

  public static function list_plugins()
  {
    self::load_plugin_admin_functions();
    self::load_update_functions();

    $updates = get_site_transient("update_plugins");
    if (!is_object($updates) && function_exists("wp_update_plugins")) {
      wp_update_plugins();
      $updates = get_site_transient("update_plugins");
    }

    $update_response = is_object($updates) && isset($updates->response) && is_array($updates->response)
      ? $updates->response
      : [];

    $items = [];
    foreach (get_plugins() as $file => $plugin) {
      $update = isset($update_response[$file]) ? $update_response[$file] : null;
      $items[] = [
        "file" => $file,
        "slug" => self::plugin_slug($file),
        "name" => isset($plugin["Name"]) ? $plugin["Name"] : self::plugin_slug($file),
        "description" => isset($plugin["Description"]) ? wp_strip_all_tags($plugin["Description"]) : "",
        "version" => isset($plugin["Version"]) ? $plugin["Version"] : "",
        "author" => isset($plugin["Author"]) ? wp_strip_all_tags($plugin["Author"]) : "",
        "pluginUri" => isset($plugin["PluginURI"]) ? esc_url_raw($plugin["PluginURI"]) : "",
        "authorUri" => isset($plugin["AuthorURI"]) ? esc_url_raw($plugin["AuthorURI"]) : "",
        "active" => is_plugin_active($file),
        "networkActive" => is_multisite() && is_plugin_active_for_network($file),
        "updateAvailable" => $update !== null,
        "newVersion" => $update && isset($update->new_version) ? $update->new_version : "",
        "canActivate" => current_user_can("activate_plugin", $file),
        "canDelete" => current_user_can("delete_plugins") && !is_plugin_active($file),
        "canUpdate" => current_user_can("update_plugins") && $update !== null,
      ];
    }

    usort($items, function ($a, $b) {
      if ($a["active"] !== $b["active"]) {
        return $a["active"] ? -1 : 1;
      }
      return strcasecmp($a["name"], $b["name"]);
    });

    return new \WP_REST_Response([
      "items" => $items,
      "stats" => self::stats($items),
      "addUrl" => admin_url("plugin-install.php"),
    ], 200);
  }

  public static function activate_plugin($request)
  {
    self::load_plugin_admin_functions();
    $file = self::sanitize_file($request->get_param("file"));
    $result = activate_plugin($file);

    if (is_wp_error($result)) {
      return new \WP_Error("aurora_plugin_activate_failed", $result->get_error_message(), ["status" => 500]);
    }

    self::clean_plugin_cache();
    return new \WP_REST_Response(["success" => true, "message" => __("Plugin activated.", "aurora-admin")], 200);
  }

  public static function deactivate_plugin($request)
  {
    self::load_plugin_admin_functions();
    $file = self::sanitize_file($request->get_param("file"));
    deactivate_plugins($file);

    self::clean_plugin_cache();
    return new \WP_REST_Response([
      "success" => true,
      "message" => __("Plugin deactivated.", "aurora-admin"),
      "redirect" => $file === "aurora-admin/aurora-admin.php" ? admin_url("plugins.php") : "",
    ], 200);
  }

  public static function delete_plugin($request)
  {
    self::load_plugin_admin_functions();
    if (!function_exists("delete_plugins")) {
      require_once ABSPATH . "wp-admin/includes/plugin.php";
    }
    // delete_plugins() calls request_filesystem_credentials() internally
    // (WP_Filesystem setup) — that's defined in file.php, which a REST API
    // request doesn't load by default the way a normal wp-admin page load
    // does. Without this, deleting a plugin fataled with "Call to
    // undefined function request_filesystem_credentials()" instead of
    // actually deleting anything. update_plugin() below already required
    // this for the same reason; delete_plugin() just never got it.
    if (!function_exists("request_filesystem_credentials")) {
      require_once ABSPATH . "wp-admin/includes/file.php";
    }

    $file = self::sanitize_file($request->get_param("file"));
    if (is_plugin_active($file)) {
      return new \WP_Error("aurora_plugin_active_delete", __("Deactivate the plugin before deleting it.", "aurora-admin"), ["status" => 400]);
    }

    $result = delete_plugins([$file]);
    if (is_wp_error($result)) {
      return new \WP_Error("aurora_plugin_delete_failed", $result->get_error_message(), ["status" => 500]);
    }

    self::clean_plugin_cache();
    return new \WP_REST_Response(["success" => true, "message" => __("Plugin deleted.", "aurora-admin")], 200);
  }

  public static function update_plugin($request)
  {
    self::load_plugin_admin_functions();
    self::load_update_functions();

    require_once ABSPATH . "wp-admin/includes/class-wp-upgrader.php";
    require_once ABSPATH . "wp-admin/includes/file.php";
    require_once ABSPATH . "wp-admin/includes/misc.php";

    $file = self::sanitize_file($request->get_param("file"));
    $skin = new \Automatic_Upgrader_Skin();
    $upgrader = new \Plugin_Upgrader($skin);
    $result = $upgrader->upgrade($file);

    if (is_wp_error($result)) {
      return new \WP_Error("aurora_plugin_update_failed", $result->get_error_message(), ["status" => 500]);
    }
    if (!$result) {
      $error = $skin->get_errors();
      $message = is_wp_error($error) && $error->has_errors()
        ? $error->get_error_message()
        : __("Plugin update failed.", "aurora-admin");
      return new \WP_Error("aurora_plugin_update_failed", $message, ["status" => 500]);
    }

    self::clean_plugin_cache();
    return new \WP_REST_Response(["success" => true, "message" => __("Plugin updated.", "aurora-admin")], 200);
  }

  private static function load_plugin_admin_functions()
  {
    if (!function_exists("get_plugins") || !function_exists("is_plugin_active")) {
      require_once ABSPATH . "wp-admin/includes/plugin.php";
    }
  }

  private static function load_update_functions()
  {
    if (!function_exists("wp_update_plugins")) {
      require_once ABSPATH . "wp-admin/includes/update.php";
    }
  }

  private static function sanitize_file($file)
  {
    return plugin_basename(sanitize_text_field((string) $file));
  }

  private static function plugin_slug($file)
  {
    $parts = explode("/", $file);
    return $parts[0];
  }

  private static function clean_plugin_cache()
  {
    if (function_exists("wp_clean_plugins_cache")) {
      wp_clean_plugins_cache(true);
    }
    wp_cache_delete("plugins", "plugins");
  }

  private static function stats($items)
  {
    $stats = ["total" => count($items), "active" => 0, "inactive" => 0, "updates" => 0];
    foreach ($items as $item) {
      if ($item["active"]) {
        $stats["active"]++;
      } else {
        $stats["inactive"]++;
      }
      if ($item["updateAvailable"]) {
        $stats["updates"]++;
      }
    }
    return $stats;
  }
}
