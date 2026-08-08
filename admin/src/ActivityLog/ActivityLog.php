<?php
namespace AuroraAdmin\ActivityLog;

defined("ABSPATH") || exit();

/**
 * Records a real event log across core WP actions. Storage + the hook
 * listeners live here; REST list/filter lives in ActivityLogData.php.
 */
class ActivityLog
{
  const TABLE = "aurora_admin_activity_log";
  const DB_VERSION = "2";
  const DB_OPTION = "aurora_admin_activity_log_db_version";

  public function __construct()
  {
    add_action("init", [self::class, "maybe_install"]);

    add_action("save_post", [self::class, "on_save_post"], 10, 3);
    add_action("before_delete_post", [self::class, "on_delete_post"]);
    add_action("add_attachment", [self::class, "on_add_attachment"]);
    add_action("delete_attachment", [self::class, "on_delete_attachment"]);
    add_action("user_register", [self::class, "on_user_register"]);
    add_action("profile_update", [self::class, "on_profile_update"]);
    add_action("wp_login", [self::class, "on_login"], 10, 2);
    add_action("wp_logout", [self::class, "on_logout"]);
    add_action("activated_plugin", [self::class, "on_plugin_activated"]);
    add_action("deactivated_plugin", [self::class, "on_plugin_deactivated"]);
    add_action("switch_theme", [self::class, "on_theme_switch"]);
    add_action("update_option_aurora_admin_settings", [self::class, "on_settings_updated"]);
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
      action_type VARCHAR(60) NOT NULL DEFAULT '',
      object_type VARCHAR(60) NOT NULL DEFAULT '',
      object_id BIGINT UNSIGNED NULL,
      object_label VARCHAR(191) NOT NULL DEFAULT '',
      user_id BIGINT UNSIGNED NULL,
      user_name VARCHAR(191) NOT NULL DEFAULT '',
      ip_address VARCHAR(45) NULL,
      details LONGTEXT NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY idx_action (action_type),
      KEY idx_object (object_type),
      KEY idx_created (created_at)
    ) {$charset};";

    require_once ABSPATH . "wp-admin/includes/upgrade.php";
    dbDelta($sql);

    update_option(self::DB_OPTION, self::DB_VERSION);
  }

  /**
   * @param array|null $actor Overrides the "current user" attribution with
   *   an explicit ["id" => int, "name" => string] — needed for wp_logout,
   *   where wp_get_current_user() has already been reset to the logged-out
   *   (id 0) user by the time this hook fires, which would otherwise record
   *   every logout as done by nobody.
   */
  private static function record($action_type, $object_type, $object_id, $object_label, $details = [], $actor = null)
  {
    global $wpdb;
    if ($actor === null) {
      $current = wp_get_current_user();
      $actor = [
        "id" => $current && $current->exists() ? $current->ID : null,
        "name" => $current && $current->exists() ? $current->display_name : "",
      ];
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- $wpdb->insert() is WordPress's own escaping-safe API for writing to a custom table (not raw SQL); there's no core post/postmeta abstraction for Aurora's own activity-log table, so any access to it is necessarily "direct" by this sniff's definition.
    $wpdb->insert(self::table_name(), [
      "action_type" => $action_type,
      "object_type" => $object_type,
      "object_id" => $object_id,
      "object_label" => substr((string) $object_label, 0, 191),
      "user_id" => $actor["id"],
      "user_name" => $actor["name"],
      "ip_address" => self::client_ip(),
      "details" => $details ? wp_json_encode($details) : null,
      "created_at" => current_time("mysql"),
    ]);
  }

  /**
   * REMOTE_ADDR only — the actual TCP peer, not spoofable by a request
   * header. Behind a reverse proxy/load balancer this will read as the
   * proxy's own address rather than the original visitor; deliberately not
   * trusting X-Forwarded-For or similar instead, since blindly trusting a
   * client-supplied header for anything (even just display, as here) is the
   * standard IP-spoofing footgun, and this plugin has no reliable way to
   * know whether a given host's proxy is trusted.
   */
  private static function client_ip()
  {
    $ip = isset($_SERVER["REMOTE_ADDR"]) ? sanitize_text_field(wp_unslash($_SERVER["REMOTE_ADDR"])) : "";
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
  }

  public static function on_save_post($post_id, $post, $update)
  {
    // save_post fires on autosaves, revisions, and attachments too — none
    // represent a real user-visible content change (attachments get their
    // own add_attachment/delete_attachment hooks below), so all three are
    // skipped here.
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) || $post->post_type === "attachment") {
      return;
    }
    if (!in_array($post->post_status, ["publish", "draft", "pending", "future", "private"], true)) {
      return;
    }
    // object_type is the post's real type (post/page/a custom post type),
    // not a hardcoded "post" — the Activity Log's Type column needs this to
    // read correctly for anything that isn't the built-in Posts type.
    self::record($update ? "updated" : "created", $post->post_type, $post_id, $post->post_title ?: "(no title)");
  }

  public static function on_delete_post($post_id)
  {
    $post = get_post($post_id);
    if (!$post || $post->post_type === "revision" || $post->post_type === "attachment") {
      return;
    }
    self::record("deleted", $post->post_type, $post_id, $post->post_title ?: "(no title)");
  }

  public static function on_add_attachment($post_id)
  {
    $post = get_post($post_id);
    self::record("created", "attachment", $post_id, $post ? $post->post_title : "(no title)");
  }

  public static function on_delete_attachment($post_id)
  {
    $post = get_post($post_id);
    self::record("deleted", "attachment", $post_id, $post ? $post->post_title : "(no title)");
  }

  public static function on_user_register($user_id)
  {
    $user = get_userdata($user_id);
    // Self-registration means the visitor isn't authenticated yet at this
    // point, so wp_get_current_user() would show "not logged in" rather
    // than the account that was just created. Attributing explicitly to the
    // new account instead — including when an admin creates the account —
    // is a deliberate choice for this specific event: unlike profile edits
    // (where the acting admin, shown via the default wp_get_current_user()
    // attribution, is the more useful "who did this"), a registration event
    // is overwhelmingly self-service, so "who this account belongs to" is
    // the more useful read here, and treating both origins the same way
    // keeps this one event type's Author column consistent regardless of
    // which flow created the account.
    self::record("registered", "user", $user_id, $user ? $user->user_login : "", [], [
      "id" => $user_id,
      "name" => $user ? $user->display_name : "",
    ]);
  }

  public static function on_profile_update($user_id)
  {
    $user = get_userdata($user_id);
    self::record("profile_updated", "user", $user_id, $user ? $user->user_login : "");
  }

  public static function on_login($user_login, $user)
  {
    // Not wp_get_current_user(): wp_login fires from inside wp_signon(),
    // before wp-login.php's own form handler calls wp_set_current_user() —
    // the same ordering issue on_logout() has, just in the other direction.
    // $user is passed in for exactly this reason; use it directly instead.
    self::record("login", "user", $user->ID, $user_login, [], [
      "id" => $user->ID,
      "name" => $user->display_name,
    ]);
  }

  public static function on_logout($user_id)
  {
    $user = $user_id ? get_userdata($user_id) : false;
    $login = $user ? $user->user_login : "";
    self::record("logout", "user", $user_id ?: null, $login, [], [
      "id" => $user_id ?: null,
      "name" => $user ? $user->display_name : "",
    ]);
  }

  public static function on_plugin_activated($plugin)
  {
    self::record("activated", "plugin", null, $plugin);
  }

  public static function on_plugin_deactivated($plugin)
  {
    self::record("deactivated", "plugin", null, $plugin);
  }

  public static function on_theme_switch($new_name)
  {
    self::record("switched", "theme", null, $new_name);
  }

  public static function on_settings_updated()
  {
    self::record("updated", "settings", null, "Aurora Admin settings");
  }
}
