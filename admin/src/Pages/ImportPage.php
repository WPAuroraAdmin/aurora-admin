<?php
namespace AuroraAdmin\Pages;

defined("ABSPATH") || exit();

/**
 * Registers the Import screen (import.php) for the "full admin takeover"
 * initiative — a real Aurora-built listing, not a reframed native page.
 *
 * Only the LISTING page is rebuilt. Running an already-active importer
 * (native "Run Importer" link) still goes to admin.php?import={id} — a
 * screen registered and entirely rendered by whatever third-party importer
 * plugin owns that id (register_importer()'s own callback). That page is
 * arbitrary, unpredictable third-party UI outside this hijack's scope
 * (a different load-{hook}), and stays native, same as e.g. the block
 * editor stays native beyond its frame.
 *
 * Install/activate reuses existing, already-proven mechanisms instead of
 * new logic: WordPress core's own admin-ajax "install-plugin" action (same
 * one native's "Install Now" button triggers) for installing, and Aurora's
 * own aurora-admin/v1/plugins/activate route (see PluginsData.php) for
 * activation.
 */
class ImportPage
{
  public function __construct()
  {
    ScreenHijack::register(
      "load-import.php",
      "import", // Matches core's own capability for this screen.
      "full_admin_takeover",
      "import",
      "aurora-admin-import-root",
      null,
      null,
      function () {
        return self::build_data();
      }
    );
  }

  private static function build_data()
  {
    if (!function_exists("get_importers")) {
      require_once ABSPATH . "wp-admin/includes/import.php";
    }

    // Real, already-active importers (registered via register_importer() by
    // an active importer plugin) — global $wp_importers. get_importers()
    // returns that global as-is, which is null (not an empty array) until
    // something actually calls register_importer() — true whenever no
    // importer plugin is active, e.g. a fresh install. Native import.php
    // never hits this directly: it only ever reads $importers via isset(),
    // and PHP auto-vivifies null into an array the moment popular_importers
    // gets merged in via `$importers[$id] = [...]`, so by the time IT
    // foreach()es, an assignment has always already happened. This code
    // foreach()es the raw result directly, so it needs its own guard.
    $active = get_importers();
    if (!is_array($active)) {
      $active = [];
    }

    $can_install = current_user_can("install_plugins");
    $popular = $can_install ? wp_get_popular_importers() : [];

    $merged = [];

    foreach ($active as $id => $data) {
      $merged[] = [
        "id" => $id,
        "name" => $data[0],
        "description" => $data[1],
        "status" => "active",
        "runUrl" => esc_url_raw(self_admin_url("admin.php?import=" . $id)),
      ];
    }

    // Same de-dup + dummy-registration logic as native import.php: a
    // popular importer already covered by an active one is skipped.
    foreach ($popular as $pop_key => $pop) {
      $importer_id = isset($pop["importer-id"]) ? $pop["importer-id"] : $pop_key;
      if (isset($active[$pop_key]) || isset($active[$importer_id])) {
        continue;
      }

      $plugin_slug = isset($pop["plugin-slug"]) ? $pop["plugin-slug"] : "";
      $entry = [
        "id" => $importer_id,
        "name" => $pop["name"],
        "description" => $pop["description"],
        "slug" => $plugin_slug,
        // Deterministic regardless of current status — provided for every
        // entry so the front end never has to construct an admin URL
        // itself (this respects any custom/network admin path).
        "runUrl" => esc_url_raw(self_admin_url("admin.php?import=" . $importer_id)),
      ];

      if ($plugin_slug !== "" && file_exists(WP_PLUGIN_DIR . "/" . $plugin_slug)) {
        if (!function_exists("get_plugins")) {
          require_once ABSPATH . "wp-admin/includes/plugin.php";
        }
        $plugins = get_plugins("/" . $plugin_slug);
        if ($plugins) {
          $keys = array_keys($plugins);
          $entry["status"] = "installed_inactive";
          $entry["pluginFile"] = $plugin_slug . "/" . $keys[0];
        } else {
          $entry["status"] = "not_installed";
        }
      } else {
        $entry["status"] = "not_installed";
      }

      $merged[] = $entry;
    }

    usort($merged, function ($a, $b) {
      return strcasecmp($a["name"], $b["name"]);
    });

    // Any third-party output hooked to import_filters (a genuine, if rarely
    // used, extension point) — same capture-and-relay approach as Tools'
    // tool_box. Empty on a site with nothing hooked to it.
    ob_start();
    do_action("import_filters");
    $native_filters_html = trim((string) ob_get_clean());

    return [
      "importers" => $merged,
      "canInstallPlugins" => $can_install,
      "installNonce" => wp_create_nonce("updates"),
      "ajaxUrl" => esc_url_raw(admin_url("admin-ajax.php")),
      "pluginSearchUrl" => esc_url_raw(network_admin_url("plugin-install.php?tab=search&type=tag&s=importer")),
      "nativeFiltersHtml" => $native_filters_html,
    ];
  }
}
