<?php
namespace AuroraAdmin\Posts;

defined("ABSPATH") || exit();

/**
 * When the posts list (edit.php) is opened with ?aurora_day=YYYY-MM-DD
 * (from the dashboard's Scheduled Content calendar), constrain the main
 * query to scheduled posts on that exact day.
 */
class ScheduledDayFilter
{
  public function __construct()
  {
    add_action("pre_get_posts", [self::class, "filter"]);
    add_action("in_admin_header", [self::class, "notice"]);
  }

  private static function day()
  {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only query-string filter for the posts list (like core's own list table filters, e.g. ?post_status=), no state change; value is sanitized and further validated against a strict date pattern below.
    $day = isset($_GET["aurora_day"]) ? sanitize_text_field(wp_unslash($_GET["aurora_day"])) : "";
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) ? $day : "";
  }

  public static function filter($query)
  {
    if (!is_admin() || !$query->is_main_query()) {
      return;
    }
    if (($GLOBALS["pagenow"] ?? "") !== "edit.php") {
      return;
    }

    $day = self::day();
    if (!$day) {
      return;
    }

    [$y, $m, $d] = array_map("intval", explode("-", $day));
    $query->set("post_status", "future");
    $query->set("date_query", [["year" => $y, "month" => $m, "day" => $d]]);
  }

  /**
   * Small heading on the filtered posts list so it's clear you're viewing a
   * single day's scheduled content (with a link back to all scheduled).
   */
  public static function notice()
  {
    if (($GLOBALS["pagenow"] ?? "") !== "edit.php") {
      return;
    }
    $day = self::day();
    if (!$day) {
      return;
    }

    $label = date_i18n(get_option("date_format"), strtotime($day));
    $all = admin_url("edit.php?post_status=future");
    ?>
    <div class="notice notice-info" style="margin-top:12px;">
      <p>
        <?php printf(
          /* translators: %s: formatted date */
          esc_html__("Showing scheduled content for %s.", "aurora-admin"),
          "<strong>" . esc_html($label) . "</strong>"
        ); ?>
        &nbsp;<a href="<?php echo esc_url($all); ?>"><?php esc_html_e("View all scheduled", "aurora-admin"); ?></a>
      </p>
    </div>
    <?php
  }
}
