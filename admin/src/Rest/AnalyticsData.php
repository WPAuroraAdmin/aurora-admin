<?php
namespace AuroraAdmin\Rest;

use AuroraAdmin\Analytics\Analytics;

defined("ABSPATH") || exit();

/**
 * Aggregates the first-party pageview table into the figures the Analytics
 * tab cards need. GET aurora-admin/v1/analytics?from=YYYY-MM-DD&to=YYYY-MM-DD
 */
class AnalyticsData
{
  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/analytics", [
      "methods" => "GET",
      "callback" => [self::class, "handle"],
      "permission_callback" => function () {
        return current_user_can("read");
      },
    ]);
  }

  public static function handle($request)
  {
    global $wpdb;
    $table = Analytics::table_name();

    $from = $request->get_param("from") ?: gmdate("Y-m-d", strtotime("-7 days"));
    $to = $request->get_param("to") ?: gmdate("Y-m-d");
    $from_dt = $from . " 00:00:00";
    $to_dt = $to . " 23:59:59";

    $where = $wpdb->prepare("created_at BETWEEN %s AND %s", $from_dt, $to_dt);

    // --- Totals ---
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- {$table} is an internal, hardcoded {$wpdb->prefix}-based table name (Analytics::table_name()), not user input; {$where} was already built via $wpdb->prepare() above.
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- {$table} is an internal, hardcoded {$wpdb->prefix}-based table name (Analytics::table_name()), not user input; {$where} was already built via $wpdb->prepare() above.
    $unique = (int) $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM {$table} WHERE {$where}");

    // --- Daily series ---
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- {$table} is an internal, hardcoded {$wpdb->prefix}-based table name (Analytics::table_name()), not user input; {$where} was already built via $wpdb->prepare() above.
    $rows = $wpdb->get_results("SELECT DATE(created_at) AS d, COUNT(*) AS c FROM {$table} WHERE {$where} GROUP BY DATE(created_at)", OBJECT_K);
    $series = [];
    $cursor = strtotime($from);
    $end = strtotime($to);
    while ($cursor <= $end) {
      $key = gmdate("Y-m-d", $cursor);
      $series[] = [
        "date" => gmdate("M j", $cursor),
        "count" => isset($rows[$key]) ? (int) $rows[$key]->c : 0,
      ];
      $cursor = strtotime("+1 day", $cursor);
    }

    // --- Device breakdown ---
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- {$table} is an internal, hardcoded {$wpdb->prefix}-based table name (Analytics::table_name()), not user input; {$where} was already built via $wpdb->prepare() above.
    $devices = $wpdb->get_results("SELECT device, COUNT(*) AS c FROM {$table} WHERE {$where} GROUP BY device ORDER BY c DESC");
    $deviceUsage = [];
    foreach ($devices as $d) {
      $deviceUsage[] = [
        "device" => $d->device,
        "count" => (int) $d->c,
        "pct" => $total ? round(($d->c / $total) * 100) : 0,
      ];
    }

    // --- Top pages / referrers / countries ---
    $topPages = self::top($wpdb, $table, $where, "url", 6);
    $topReferrers = self::top($wpdb, $table, $where, "referrer", 6, true);
    $topCountries = self::top($wpdb, $table, $where, "country", 6, true);

    // --- Bounce rate & avg session depth ---
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- {$table} is an internal, hardcoded {$wpdb->prefix}-based table name (Analytics::table_name()), not user input; {$where} was already built via $wpdb->prepare() above.
    $sessionCounts = $wpdb->get_results("SELECT session_id, COUNT(*) AS c, TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) AS dur FROM {$table} WHERE {$where} GROUP BY session_id");
    $sessions = count($sessionCounts);
    $bounces = 0;
    $totalDur = 0;
    foreach ($sessionCounts as $s) {
      if ((int) $s->c <= 1) {
        $bounces++;
      }
      $totalDur += (int) $s->dur;
    }
    $bounceRate = $sessions ? round(($bounces / $sessions) * 100) : 0;
    $avgSeconds = $sessions ? round($totalDur / $sessions) : 0;

    // --- Active users (last 5 minutes) ---
    $activeCutoff = gmdate("Y-m-d H:i:s", current_time("timestamp") - 300);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} is an internal, hardcoded {$wpdb->prefix}-based table name, not user input; $activeCutoff is bound via the $wpdb->prepare() %s placeholder. Deliberately not cached — this is a live "active right now" figure.
    $active = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT session_id) FROM {$table} WHERE created_at >= %s", $activeCutoff));

    return new \WP_REST_Response([
      "pageViews" => ["total" => $total, "unique" => $unique, "series" => $series],
      "deviceUsage" => $deviceUsage,
      "topPages" => $topPages,
      "topReferrers" => $topReferrers,
      "topCountries" => $topCountries,
      "bounce" => [
        "rate" => $bounceRate,
        "avgTime" => self::format_duration($avgSeconds),
        "sessions" => $sessions,
      ],
      "activeUsers" => $active,
    ], 200);
  }

  private static function top($wpdb, $table, $where, $col, $limit, $skip_empty = false)
  {
    $extra = $skip_empty ? " AND {$col} <> ''" : "";
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is an internal, hardcoded table name; $col/$limit are internal literals passed only by this file's own three call sites above ("url"/"referrer"/"country", 6 — never from $request), not user input; $where was already built via $wpdb->prepare() by the caller.
    $results = $wpdb->get_results("SELECT {$col} AS label, COUNT(*) AS c FROM {$table} WHERE {$where}{$extra} GROUP BY {$col} ORDER BY c DESC LIMIT {$limit}");
    $out = [];
    foreach ($results as $r) {
      $out[] = ["label" => $r->label !== "" ? $r->label : "Direct", "count" => (int) $r->c];
    }
    return $out;
  }

  private static function format_duration($seconds)
  {
    if ($seconds <= 0) {
      return "0s";
    }
    $m = floor($seconds / 60);
    $s = $seconds % 60;
    return $m > 0 ? "{$m}m {$s}s" : "{$s}s";
  }
}
