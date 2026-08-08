<?php
namespace AuroraAdmin\MenuCreator;

defined("ABSPATH") || exit();

/**
 * Custom admin-menu configurations: hide/rename/reorder WP's native menu
 * items per role. Storage + the runtime hook that actually applies a
 * published config live here; REST CRUD lives in MenuCreatorData.php.
 */
class MenuCreator
{
  const TABLE = "aurora_admin_menus";
  const DB_VERSION = "1";
  const DB_OPTION = "aurora_admin_menus_db_version";

  /** Cached merged item list for the current user (per request). */
  private static $items_cache = null;

  /** Raw top-level menu snapshot, captured before apply() hides anything. */
  private static $native_snapshot = null;

  public function __construct()
  {
    add_action("init", [self::class, "maybe_install"]);
    // Snapshot the full native menu just before apply() runs, so the editor
    // can list (and re-enable) items this config hides. Both run after every
    // other plugin has registered its menu items.
    add_action("admin_menu", [self::class, "capture_native"], PHP_INT_MAX - 1);
    add_action("admin_menu", [self::class, "apply"], PHP_INT_MAX);
  }

  /** Captures the raw top-level menu for the editor (see native_snapshot()). */
  public static function capture_native()
  {
    self::$native_snapshot = \AuroraAdmin\Shell\MenuSerializer::top_level_items();
  }

  /**
   * The raw top-level menu items for the current admin page load. The Menu
   * Creator page feeds this into its serverData (SubApps.php) because the
   * REST route can't build the admin menu.
   */
  public static function native_snapshot()
  {
    return is_array(self::$native_snapshot) ? self::$native_snapshot : [];
  }

  public static function table_name()
  {
    global $wpdb;
    return $wpdb->prefix . self::TABLE;
  }

  public static function maybe_install()
  {
    if (get_option(self::DB_OPTION) === self::DB_VERSION) {
      return;
    }

    global $wpdb;
    $table = self::table_name();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(191) NOT NULL DEFAULT '',
      status VARCHAR(20) NOT NULL DEFAULT 'draft',
      roles TEXT NULL,
      items LONGTEXT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY idx_status (status)
    ) {$charset};";

    require_once ABSPATH . "wp-admin/includes/upgrade.php";
    dbDelta($sql);

    update_option(self::DB_OPTION, self::DB_VERSION);
  }

  /**
   * Merged item list from every published config whose target roles match
   * the current user. Cached for the request (read by both apply() and
   * ordered_slugs() on every admin page load).
   */
  private static function current_user_items()
  {
    if (self::$items_cache !== null) {
      return self::$items_cache;
    }
    self::$items_cache = [];

    global $wpdb;
    if (!is_admin() || !function_exists("wp_get_current_user")) {
      return self::$items_cache;
    }

    $user = wp_get_current_user();
    if (!$user || !$user->exists()) {
      return self::$items_cache;
    }

    $table = self::table_name();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} is an internal, hardcoded {$wpdb->prefix}-based table name, not user input; no user-supplied values in this query. Runs on every admin page load to build the sidebar menu, so a per-request cache wouldn't help; a persistent cache would need its own invalidation on every config save, more complexity than this read is worth.
    $configs = $wpdb->get_results("SELECT roles, items FROM {$table} WHERE status = 'published'");
    if (!$configs) {
      return self::$items_cache;
    }

    $items = [];
    foreach ($configs as $config) {
      $roles = json_decode($config->roles ?: "[]", true);
      if (!is_array($roles) || !$roles || !array_intersect((array) $user->roles, $roles)) {
        continue;
      }
      $decoded = json_decode($config->items ?: "[]", true);
      if (is_array($decoded)) {
        $items = array_merge($items, $decoded);
      }
    }

    self::$items_cache = $items;
    return $items;
  }

  /**
   * Hides items marked hidden and relabels items with a custom label, on the
   * native top-level $menu (which Aurora's MenuSerializer then reads).
   */
  public static function apply()
  {
    global $menu;
    if (!is_array($menu)) {
      return;
    }

    foreach (self::current_user_items() as $item) {
      $native_id = isset($item["native_id"]) ? $item["native_id"] : "";
      if ($native_id === "") {
        continue;
      }

      foreach ($menu as $key => $entry) {
        if (!isset($entry[2]) || $entry[2] !== $native_id) {
          continue;
        }

        if (!empty($item["hidden"])) {
          unset($menu[$key]);
          continue 2;
        }

        if (!empty($item["label"])) {
          $menu[$key][0] = sanitize_text_field($item["label"]);
        }
      }
    }
  }

  /**
   * Desired top-level slug order for the current user, from the saved config
   * (items sorted by their 'order' field). Empty when no config applies.
   *
   * Consumed by MenuSerializer so Aurora's own sidebar reflects the order —
   * WordPress's native `menu_order` filter only reorders the core admin menu,
   * which Aurora hides, so it can't be used here.
   */
  public static function ordered_slugs()
  {
    $items = self::current_user_items();
    if (!$items) {
      return [];
    }

    usort($items, function ($a, $b) {
      $ao = isset($a["order"]) ? (int) $a["order"] : 0;
      $bo = isset($b["order"]) ? (int) $b["order"] : 0;
      return $ao <=> $bo;
    });

    $slugs = [];
    foreach ($items as $item) {
      $slug = isset($item["native_id"]) ? $item["native_id"] : "";
      if ($slug !== "" && !in_array($slug, $slugs, true)) {
        $slugs[] = $slug;
      }
    }
    return $slugs;
  }
}
