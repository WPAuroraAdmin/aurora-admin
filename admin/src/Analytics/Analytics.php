<?php
namespace AuroraAdmin\Analytics;

defined("ABSPATH") || exit();

/**
 * Lightweight first-party visitor analytics.
 *
 * Records one row per front-end pageview into a custom table, server-side
 * (no client script, so it isn't blocked by ad blockers). All aggregation
 * happens later in the REST endpoint. Written fresh — does not reuse the
 * original plugin's analytics code.
 */
class Analytics
{
  const TABLE = "aurora_admin_pageviews";
  const DB_VERSION = "1";
  const DB_OPTION = "aurora_admin_analytics_db_version";
  const COOKIE = "aurora_admin_sid";

  public function __construct()
  {
    add_action("init", [self::class, "maybe_install"]);
    // Record on the front end only, after the main query is resolved.
    add_action("template_redirect", [self::class, "record"], 999);
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
      session_id VARCHAR(32) NOT NULL DEFAULT '',
      url VARCHAR(255) NOT NULL DEFAULT '',
      referrer VARCHAR(255) NOT NULL DEFAULT '',
      device VARCHAR(20) NOT NULL DEFAULT 'desktop',
      browser VARCHAR(40) NOT NULL DEFAULT '',
      country VARCHAR(2) NOT NULL DEFAULT '',
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY idx_created (created_at),
      KEY idx_session (session_id)
    ) {$charset};";

    require_once ABSPATH . "wp-admin/includes/upgrade.php";
    dbDelta($sql);

    update_option(self::DB_OPTION, self::DB_VERSION);
  }

  /**
   * Records the current front-end request as a pageview, skipping admin,
   * AJAX/REST/cron, bots, and logged-in administrators.
   */
  public static function record()
  {
    // Tracking is on unless explicitly disabled in Settings > General.
    $enabled = \AuroraAdmin\Options\Settings::get("enable_analytics", true);
    if ($enabled === false || $enabled === 0 || $enabled === "0") {
      return;
    }

    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || is_robots() || is_feed()) {
      return;
    }
    if (defined("REST_REQUEST") && REST_REQUEST) {
      return;
    }

    // Admins browsing their own site are skipped unless Settings >
    // Analytics > "Track logged-in admins" is enabled.
    $track_admins = \AuroraAdmin\Options\Settings::get("track_admins", false);
    if (!$track_admins && current_user_can("manage_options")) {
      return;
    }

    $ua = isset($_SERVER["HTTP_USER_AGENT"]) ? sanitize_text_field(wp_unslash($_SERVER["HTTP_USER_AGENT"])) : "";
    if (self::is_bot($ua)) {
      return;
    }

    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->insert() is WordPress's own escaping-safe API for writing to Aurora's custom pageview table.
    $wpdb->insert(self::table_name(), [
      "session_id" => self::session_id(),
      "url" => substr(self::current_path(), 0, 255),
      "referrer" => substr(self::referrer(), 0, 255),
      "device" => self::device($ua),
      "browser" => self::browser($ua),
      "country" => self::country(),
      "created_at" => current_time("mysql"),
    ]);
  }

  private static function session_id()
  {
    if (!empty($_COOKIE[self::COOKIE])) {
      $cookie = sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE]));
      return preg_replace("/[^a-f0-9]/", "", substr($cookie, 0, 32));
    }
    $sid = md5(uniqid("", true));
    // 30-minute rolling session window
    if (!headers_sent()) {
      setcookie(self::COOKIE, $sid, time() + 1800, COOKIEPATH ?: "/", COOKIE_DOMAIN);
    }
    $_COOKIE[self::COOKIE] = $sid;
    return $sid;
  }

  private static function current_path()
  {
    $uri = isset($_SERVER["REQUEST_URI"]) ? sanitize_text_field(wp_unslash($_SERVER["REQUEST_URI"])) : "/";
    return wp_parse_url($uri, PHP_URL_PATH) ?: "/";
  }

  private static function referrer()
  {
    $ref = isset($_SERVER["HTTP_REFERER"]) ? sanitize_text_field(wp_unslash($_SERVER["HTTP_REFERER"])) : "";
    if (!$ref) {
      return "";
    }
    $host = wp_parse_url($ref, PHP_URL_HOST);
    // Ignore self-referrals
    if ($host && $host === wp_parse_url(home_url(), PHP_URL_HOST)) {
      return "";
    }
    return $host ?: "";
  }

  private static function device($ua)
  {
    $ua = strtolower($ua);
    if (preg_match("/(ipad|tablet|playbook|silk)|(android(?!.*mobile))/", $ua)) {
      return "tablet";
    }
    if (preg_match("/(mobile|iphone|ipod|android|blackberry|opera mini|iemobile)/", $ua)) {
      return "mobile";
    }
    return "desktop";
  }

  private static function browser($ua)
  {
    $map = [
      "Edg" => "Edge",
      "OPR" => "Opera",
      "Chrome" => "Chrome",
      "Firefox" => "Firefox",
      "Safari" => "Safari",
    ];
    foreach ($map as $needle => $name) {
      if (stripos($ua, $needle) !== false) {
        return $name;
      }
    }
    return "Other";
  }

  private static function country()
  {
    // Use a CDN-provided country header if available; otherwise leave blank.
    // Sanitized into $value before any check runs on it — the empty()/
    // strlen() checks below need the unslashed+sanitized copy too, not
    // just the eventual return value, since reading the raw superglobal
    // for any purpose (even just a length check) is what the sanitization
    // rule actually flags.
    foreach (["HTTP_CF_IPCOUNTRY", "GEOIP_COUNTRY_CODE", "HTTP_X_COUNTRY_CODE"] as $h) {
      $value = isset($_SERVER[$h]) ? sanitize_text_field(wp_unslash($_SERVER[$h])) : "";
      if ($value !== "" && strlen($value) === 2) {
        return strtoupper($value);
      }
    }
    return "";
  }

  private static function is_bot($ua)
  {
    if (!$ua) {
      return true;
    }
    return (bool) preg_match(
      "/bot|crawl|slurp|spider|mediapartners|facebookexternalhit|embedly|quora|pingdom|monitor|headless|lighthouse|gtmetrix/i",
      $ua
    );
  }
}
