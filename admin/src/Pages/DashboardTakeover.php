<?php
namespace AuroraAdmin\Pages;

use AuroraAdmin\Utility\Assets;
use AuroraAdmin\Options\Settings;
use AuroraAdmin\Notices\Notices;

defined("ABSPATH") || exit();

/**
 * Replaces the native WordPress dashboard (index.php) with Aurora's own
 * dashboard, the way the original plugin did.
 *
 * Rather than removing/moving WordPress's dashboard DOM, we render the
 * Aurora mount point at the very top of the content area (the
 * `in_admin_header` hook fires there, before the native .wrap) and simply
 * hide the native dashboard's .wrap with CSS. Clean and reversible — if
 * this plugin is disabled, the native dashboard returns untouched.
 */
class DashboardTakeover
{
  public function __construct()
  {
    add_action("admin_enqueue_scripts", [self::class, "maybe_enqueue"]);
    add_action("admin_head", [self::class, "hide_native"]);
    add_action("in_admin_header", [self::class, "render_mount"]);
  }

  public static function is_dashboard()
  {
    // Settings > Dashboard > "Custom dashboard" — previously always on
    // regardless of this setting; default true so existing installs keep
    // their current behavior until explicitly turned off.
    if (!Settings::get("use_custom_dashboard", true)) {
      return false;
    }

    return isset($GLOBALS["pagenow"]) && $GLOBALS["pagenow"] === "index.php";
  }

  public static function maybe_enqueue()
  {
    if (!self::is_dashboard()) {
      return;
    }

    Assets::enqueue("aurora-admin-dashboard", "entries/dashboard.js");
  }

  public static function hide_native()
  {
    if (!self::is_dashboard()) {
      return;
    }
    ?>
    <style id="aurora-admin-dashboard-takeover">
      /* The Aurora dashboard is rendered in #wpcontent (above #wpbody) via
         in_admin_header, so the entire native #wpbody — heading, welcome
         panel, widgets, and the Screen Options / Help tabs inside it — is
         hidden. This leaves just the Aurora dashboard with no empty native
         frame below it. Printed only on the dashboard screen. */
      html.aurora-active #wpbody {
        display: none !important;
      }
    </style>
    <?php
  }

  public static function render_mount()
  {
    if (!self::is_dashboard()) {
      return;
    }

    $current_user = wp_get_current_user();

    $data = [
      "restUrl" => esc_url_raw(get_rest_url()),
      "restNonce" => wp_create_nonce("wp_rest"),
      "userName" => $current_user->display_name,
      "settings" => Settings::get_safe(),
    ];
    ?>
    <div id="aurora-admin-dashboard-notices">
      <?php Notices::render_for_dashboard(); ?>
    </div>
    <div
      id="aurora-admin-dashboard-root"
      data-aurora-admin="<?php echo esc_attr(wp_json_encode($data)); ?>"
    ></div>
    <?php
  }
}
