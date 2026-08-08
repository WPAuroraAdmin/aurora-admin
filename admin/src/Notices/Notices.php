<?php
namespace AuroraAdmin\Notices;

use AuroraAdmin\Pages\DashboardTakeover;

defined("ABSPATH") || exit();

/**
 * Custom admin notices: storage + the runtime hook that actually renders
 * active notices matching the current user/page. REST CRUD lives in
 * NoticesData.php.
 */
class Notices
{
  const TABLE = "aurora_admin_notices";
  const DB_VERSION = "1";
  const DB_OPTION = "aurora_admin_notices_db_version";

  public function __construct()
  {
    add_action("init", [self::class, "maybe_install"]);
    add_action("admin_notices", [self::class, "render"]);
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
      title VARCHAR(191) NOT NULL DEFAULT '',
      message LONGTEXT NULL,
      type VARCHAR(20) NOT NULL DEFAULT 'info',
      roles TEXT NULL,
      pages TEXT NULL,
      dismissible TINYINT(1) NOT NULL DEFAULT 1,
      active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY idx_active (active)
    ) {$charset};";

    require_once ABSPATH . "wp-admin/includes/upgrade.php";
    dbDelta($sql);

    update_option(self::DB_OPTION, self::DB_VERSION);
  }

  /**
   * Hooked to admin_notices, which fires everywhere EXCEPT it doesn't
   * actually reach the user on the Dashboard: admin_notices normally
   * prints inside #wpbody, and DashboardTakeover hides #wpbody entirely
   * there to hide the *native* dashboard widgets, taking custom notices
   * down with it as collateral damage. render_for_dashboard() (called
   * directly by DashboardTakeover, inside its own visible mount point) is
   * the fix for that one case — skip the normal path here so it isn't
   * rendered (invisibly) twice.
   */
  public static function render()
  {
    if (DashboardTakeover::is_dashboard()) {
      return;
    }

    self::render_notices(false);
  }

  /**
   * Called directly by DashboardTakeover::render_mount(), inside the
   * visible dashboard frame, since the normal admin_notices hook never
   * reaches the user there (see render()).
   */
  public static function render_for_dashboard()
  {
    self::render_notices(true);
  }

  /**
   * $keep_in_place matters because of a WP core behavior, not a bug of
   * ours: wp-admin/js/common.js unconditionally relocates every
   * `.notice`/`.updated`/`.error` element on the page to right after the
   * page's <h1> ($('div.notice').not('.inline, .below-h2').insertAfter(...)),
   * regardless of where it was originally placed in the DOM. On every
   * other page that's harmless (it's already heading there anyway); on
   * the Dashboard it silently moves our notice into the native #wpbody,
   * which is hidden entirely by DashboardTakeover — invisible, not gone.
   * `below-h2` is core's own documented opt-out class for exactly this
   * "leave this notice exactly where I put it" case.
   */
  private static function render_notices($keep_in_place)
  {
    global $wpdb;
    $user = wp_get_current_user();
    if (!$user || !$user->exists()) {
      return;
    }

    $current_page = isset($GLOBALS["pagenow"]) ? $GLOBALS["pagenow"] : "";
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- table name from an internal, hardcoded {$wpdb->prefix}-based constant, not user input; no user-supplied values in this query. Runs on every admin page load to decide which notices to show, so caching would need its own invalidation on every notice save — more complexity than this read is worth.
    $rows = $wpdb->get_results("SELECT * FROM " . self::table_name() . " WHERE active = 1");

    foreach ($rows as $row) {
      $roles = json_decode($row->roles ?: "[]", true);
      if (is_array($roles) && $roles && !array_intersect((array) $user->roles, $roles)) {
        continue;
      }

      $pages = json_decode($row->pages ?: "[]", true);
      if (is_array($pages) && $pages && !in_array($current_page, $pages, true)) {
        continue;
      }

      $type = in_array($row->type, ["success", "warning", "error", "info"], true) ? $row->type : "info";
      $classes = "notice notice-{$type} aurora-native-notice";
      if ($keep_in_place) {
        $classes .= " below-h2";
      }
      if ($row->dismissible) {
        $classes .= " is-dismissible";
      }

      printf(
        '<div class="%1$s"><p><strong>%2$s</strong>%3$s</p></div>',
        esc_attr($classes),
        esc_html($row->title),
        $row->message ? ": " . esc_html($row->message) : ""
      );
    }
  }
}
