<?php
namespace AuroraAdmin\Rest;

defined("ABSPATH") || exit();

/**
 * REST endpoint that supplies all data for the Aurora dashboard cards.
 * GET aurora-admin/v1/dashboard?from=YYYY-MM-DD&to=YYYY-MM-DD
 *
 * Everything is computed fresh here from core WordPress APIs — no reuse of
 * the original plugin's data layer.
 */
class DashboardData
{
  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/dashboard", [
      "methods" => "GET",
      "callback" => [self::class, "handle"],
      "permission_callback" => function () {
        return current_user_can("read");
      },
      "args" => [
        "from" => ["type" => "string", "required" => false],
        "to" => ["type" => "string", "required" => false],
      ],
    ]);
  }

  public static function handle($request)
  {
    $to = self::parse_date($request->get_param("to"), "now");
    $from = self::parse_date($request->get_param("from"), "-7 days");

    return new \WP_REST_Response([
      "recentContent" => self::recent_content(),
      "scheduledContent" => self::scheduled_content(),
      "recentComments" => self::recent_comments(),
      "userAnalytics" => self::user_analytics($from, $to),
      "mediaAnalytics" => self::media_analytics(),
      "serverHealth" => self::server_health(),
    ], 200);
  }

  private static function parse_date($value, $fallback)
  {
    $ts = $value ? strtotime($value) : false;
    if ($ts === false) {
      $ts = strtotime($fallback);
    }
    return $ts;
  }

  private static function recent_content()
  {
    $counts = wp_count_posts("post");
    $recent = get_posts([
      "post_type" => "post",
      "post_status" => ["publish", "draft", "future"],
      "numberposts" => 5,
      "orderby" => "date",
      "order" => "DESC",
    ]);

    $latest = [];
    foreach ($recent as $p) {
      $latest[] = [
        "id" => $p->ID,
        "title" => get_the_title($p),
        "author" => get_the_author_meta("display_name", $p->post_author),
        "date" => get_the_date("M j, Y", $p),
        "status" => $p->post_status,
        "editUrl" => get_edit_post_link($p->ID, "raw"),
      ];
    }

    return [
      "published" => (int) $counts->publish,
      "draft" => (int) $counts->draft,
      "scheduled" => (int) $counts->future,
      "total" => (int) $counts->publish + (int) $counts->draft + (int) $counts->future,
      "latest" => $latest,
      "listUrl" => admin_url("edit.php"),
    ];
  }

  private static function scheduled_content()
  {
    $future = get_posts([
      "post_type" => "post",
      "post_status" => "future",
      "numberposts" => 100,
      "orderby" => "date",
      "order" => "ASC",
    ]);

    $days = [];
    foreach ($future as $p) {
      $days[get_the_date("Y-m-d", $p)][] = [
        "id" => $p->ID,
        "title" => get_the_title($p),
        "time" => get_the_time("g:i a", $p),
      ];
    }

    return [
      "count" => count($future),
      "days" => $days,
    ];
  }

  private static function recent_comments()
  {
    $counts = wp_count_comments();
    $recent = get_comments([
      "number" => 5,
      "status" => "approve",
      "orderby" => "comment_date_gmt",
      "order" => "DESC",
    ]);

    $latest = [];
    foreach ($recent as $c) {
      $latest[] = [
        "id" => $c->comment_ID,
        "author" => $c->comment_author,
        "excerpt" => wp_trim_words($c->comment_content, 18),
        "postTitle" => get_the_title($c->comment_post_ID),
        "date" => mysql2date("M j, Y", $c->comment_date),
      ];
    }

    return [
      "approved" => (int) $counts->approved,
      "pending" => (int) $counts->moderated,
      "spam" => (int) $counts->spam,
      "total" => (int) $counts->approved,
      "latest" => $latest,
      "listUrl" => admin_url("admin.php?page=aurora-admin-comments"),
    ];
  }

  private static function user_analytics($from, $to)
  {
    $total = count_users();
    $total_users = (int) $total["total_users"];

    $from_date = gmdate("Y-m-d 00:00:00", $from);
    $to_date = gmdate("Y-m-d 23:59:59", $to);

    $in_range = get_users([
      "date_query" => [["after" => $from_date, "before" => $to_date, "inclusive" => true]],
      "fields" => "ID",
    ]);

    $recent7 = get_users([
      "date_query" => [["after" => "-7 days", "inclusive" => true]],
      "fields" => "ID",
    ]);

    // Daily registration series across the range
    $series = [];
    $cursor = strtotime(gmdate("Y-m-d", $from));
    $end = strtotime(gmdate("Y-m-d", $to));
    $by_day = [];
    foreach ($in_range as $uid) {
      $reg = get_userdata($uid)->user_registered;
      $by_day[mysql2date("Y-m-d", $reg)] = (isset($by_day[mysql2date("Y-m-d", $reg)]) ? $by_day[mysql2date("Y-m-d", $reg)] : 0) + 1;
    }
    while ($cursor <= $end) {
      $key = gmdate("Y-m-d", $cursor);
      $series[] = [
        "date" => gmdate("M j", $cursor),
        "count" => isset($by_day[$key]) ? $by_day[$key] : 0,
      ];
      $cursor = strtotime("+1 day", $cursor);
    }

    return [
      "total" => $total_users,
      "inRange" => count($in_range),
      "recent7d" => count($recent7),
      "series" => $series,
    ];
  }

  private static function media_analytics()
  {
    $attachments = get_posts([
      "post_type" => "attachment",
      "post_status" => "inherit",
      "numberposts" => -1,
      "fields" => "ids",
    ]);

    $total_size = 0;
    $large = 0;
    $recent30 = 0;
    $thirty_ago = strtotime("-30 days");

    foreach ($attachments as $id) {
      $file = get_attached_file($id);
      $size = $file && file_exists($file) ? filesize($file) : 0;
      $total_size += $size;
      if ($size > 5 * 1024 * 1024) {
        $large++;
      }
      if (strtotime(get_post_field("post_date", $id)) >= $thirty_ago) {
        $recent30++;
      }
    }

    return [
      "totalFiles" => count($attachments),
      "totalSize" => self::format_bytes($total_size),
      "recent30d" => $recent30,
      "unused" => 0,
      "largeFiles" => $large,
      "diskUsage" => self::format_bytes($total_size),
      "manageUrl" => admin_url("upload.php"),
    ];
  }

  private static function server_health()
  {
    $mem_limit = self::to_bytes(ini_get("memory_limit"));
    $mem_used = memory_get_usage(true);
    $disk_total = @disk_total_space(ABSPATH) ?: 0;
    $disk_free = @disk_free_space(ABSPATH) ?: 0;
    $disk_used = $disk_total - $disk_free;

    $updates = function_exists("wp_get_update_data") ? wp_get_update_data() : ["counts" => []];
    $counts = isset($updates["counts"]) ? $updates["counts"] : [];

    return [
      "wp" => get_bloginfo("version"),
      "php" => PHP_VERSION,
      "memory" => [
        "used" => self::format_bytes($mem_used),
        "total" => self::format_bytes($mem_limit),
        "pct" => $mem_limit > 0 ? round(($mem_used / $mem_limit) * 100, 1) : 0,
      ],
      "disk" => [
        "used" => self::format_bytes($disk_used),
        "total" => self::format_bytes($disk_total),
        "pct" => $disk_total > 0 ? round(($disk_used / $disk_total) * 100, 1) : 0,
      ],
      "plugins" => isset($counts["plugins"]) ? (int) $counts["plugins"] : 0,
      "themes" => isset($counts["themes"]) ? (int) $counts["themes"] : 0,
      "core" => isset($counts["wordpress"]) ? (int) $counts["wordpress"] : 0,
      "updates" => isset($counts["total"]) ? (int) $counts["total"] : 0,
      "ssl" => is_ssl(),
      "timezone" => wp_timezone_string(),
    ];
  }

  private static function format_bytes($bytes)
  {
    $bytes = (float) $bytes;
    $units = ["B", "KB", "MB", "GB", "TB"];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
      $bytes /= 1024;
      $i++;
    }
    return round($bytes, 2) . " " . $units[$i];
  }

  private static function to_bytes($val)
  {
    $val = trim($val);
    if ($val === "" || $val === "-1") {
      return 0;
    }
    $last = strtolower($val[strlen($val) - 1]);
    $num = (float) $val;
    switch ($last) {
      case "g":
        $num *= 1024;
      case "m":
        $num *= 1024;
      case "k":
        $num *= 1024;
    }
    return $num;
  }
}
